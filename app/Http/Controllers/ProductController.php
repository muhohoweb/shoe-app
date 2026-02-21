<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Faker\Provider\Image as ImageIntervention;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // Simple dynamic upload path
    private function getUploadPath()
    {
        // Based on your server output, this will work for ALL your subdomains
        return $_SERVER['DOCUMENT_ROOT'] . '/uploads';
    }

    public function index()
    {
        return Inertia::render('products/Index', [
            'products' => Product::with('category', 'images')->latest()->paginate(10),
            'categories' => Category::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'colors' => 'required|array',
            'sizes' => 'required|array',
            'images' => 'nullable|array|max:3',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $product = Product::query()->create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'colors' => $validated['colors'],
            'sizes' => $validated['sizes'],
            'slug' => $slug,
            'sku' => 'SKU-' . strtoupper(Str::random(8)),
        ]);

        $this->uploadImages($product, $request->file('images') ?? []);

        return redirect()->route('products.index');
    }

    public function update(Request $request, string $id)
    {
        $product = Product::query()->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'colors' => 'required|array',
            'sizes' => 'required|array',
            'images' => 'nullable|array|max:3',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
            'removed_image_ids' => 'nullable|array',
            'removed_image_ids.*' => 'nullable|integer',
        ]);

        if ($product->name !== $validated['name']) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Product::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $validated['slug'] = $slug;
        }

        $product->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'colors' => $validated['colors'],
            'sizes' => $validated['sizes'],
            'slug' => $validated['slug'] ?? $product->slug,
        ]);

        // Delete removed images
        if (!empty($validated['removed_image_ids'])) {
            $uploadPath = $this->getUploadPath();
            $removedImages = $product->images()->whereIn('id', $validated['removed_image_ids'])->get();
            foreach ($removedImages as $image) {
                $imagePath = $uploadPath . '/' . basename($image->path);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $product->images()->whereIn('id', $validated['removed_image_ids'])->delete();
        }

        // Upload new images
        $this->uploadImages($product, $request->file('images') ?? []);

        return redirect()->route('products.index');
    }

    public function destroy(string $id)
    {
        $product = Product::query()->findOrFail($id);
        $uploadPath = $this->getUploadPath();

        // Delete images
        foreach ($product->images as $image) {
            $imagePath = $uploadPath . '/' . basename($image->path);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $product->delete();

        return redirect()->route('products.index');
    }

    private function uploadImages(Product $product, array $images): void
    {
        $uploadPath = $this->getUploadPath();

        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        foreach ($images as $image) {
            if (!$image) continue;

            $filename = time() . '_' . Str::random(20) . '.webp';

            // Resize and compress
            ImageIntervention::read($image)
                ->scaleDown(width: 1200)        // max width 1200px, keeps aspect ratio
                ->toWebp(quality: 80)           // convert to webp at 80% quality
                ->save($uploadPath . '/' . $filename);

            $product->images()->create([
                'path' => 'uploads/' . $filename,
            ]);
        }
    }
}