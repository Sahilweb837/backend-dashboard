<?php
namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::orderBy('created_at', 'desc')->get();
        
        // Convert storage paths to full URLs
        $blogs->transform(function ($blog) {
            if ($blog->image && !filter_var($blog->image, FILTER_VALIDATE_URL)) {
                $blog->image = asset($blog->image);
            }
            return $blog;
        });
        
        return response()->json($blogs);
    }

    public function show($id)
    {
        $blog = Blog::findOrFail($id);
        
        // Convert storage path to full URL
        if ($blog->image && !filter_var($blog->image, FILTER_VALIDATE_URL)) {
            $blog->image = asset($blog->image);
        }
        
        return response()->json($blog);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required',
            'author' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $imagePath = $this->handleImageUpload($request->image);

            $blog = Blog::create([
                'title' => $request->title,
                'description' => $request->description,
                'image' => $imagePath,
                'author' => $request->author
            ]);

            // Convert storage path to full URL for response
            if (!filter_var($blog->image, FILTER_VALIDATE_URL)) {
                $blog->image = asset($blog->image);
            }

            return response()->json($blog, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Image upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $blog = Blog::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required',
            'author' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $oldImage = $blog->image;
            $imagePath = $this->handleImageUpload($request->image, $oldImage);

            $blog->update([
                'title' => $request->title,
                'description' => $request->description,
                'image' => $imagePath,
                'author' => $request->author
            ]);

            // Convert storage path to full URL for response
            if (!filter_var($blog->image, FILTER_VALIDATE_URL)) {
                $blog->image = asset($blog->image);
            }

            return response()->json($blog);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Image upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $blog = Blog::findOrFail($id);
        
        // Delete image file if it's not a URL and exists in storage
        if ($blog->image && !filter_var($blog->image, FILTER_VALIDATE_URL)) {
            $imagePath = str_replace(asset(''), '', $blog->image);
            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
        }
        
        $blog->delete();
        return response()->json(['message' => 'Blog deleted successfully']);
    }

    private function handleImageUpload($image, $oldImage = null)
    {
        // If image is a URL, return it directly
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        // If image is base64 encoded
        if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
            $imageData = substr($image, strpos($image, ',') + 1);
            $type = strtolower($type[1]); // jpg, jpeg, png, gif

            // Validate image type
            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new \Exception('Invalid image type. Allowed: jpg, jpeg, png, gif, webp');
            }

            $imageData = str_replace(' ', '+', $imageData);
            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                throw new \Exception('base64_decode failed');
            }

            // Validate file size (max 5MB)
            if (strlen($imageData) > 5 * 1024 * 1024) {
                throw new \Exception('Image size should be less than 5MB');
            }
        } else {
            throw new \Exception('Invalid image format');
        }

        // Delete old image if it exists and is not a URL
        if ($oldImage && !filter_var($oldImage, FILTER_VALIDATE_URL)) {
            $oldImagePath = str_replace(asset(''), '', $oldImage);
            if (Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }
        }

        // Generate unique filename
        $filename = 'blog_' . time() . '_' . uniqid() . '.' . $type;
        $filePath = 'blogs/' . $filename;

        // Save the image
        Storage::disk('public')->put($filePath, $imageData);

        return 'storage/' . $filePath;
    }
}