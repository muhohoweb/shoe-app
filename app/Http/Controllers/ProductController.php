<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ProductController extends Controller
{
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
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
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
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
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

        // Delete removed images from disk and DB
        if (!empty($validated['removed_image_ids'])) {
            $removedImages = $product->images()->whereIn('id', $validated['removed_image_ids'])->get();
            foreach ($removedImages as $image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($image->path);
            }
            $product->images()->whereIn('id', $validated['removed_image_ids'])->delete();
        }

        // Upload new images
        $this->uploadImages($product, $request->file('images') || []);

        return redirect()->route('products.index');
    }

    public function destroy(string $id)
    {
        $product = Product::query()->findOrFail($id);

        // Delete images from disk before deleting the product
        foreach ($product->images as $image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($image->path);
        }

        $product->delete();

        return redirect()->route('products.index');
    }

    private function uploadImages(Product $product, array $images): void
    {
        foreach ($images as $image) {
            if (!$image) continue;

            $path = $image->store('products', 'public');

            $product->images()->create([
                'path' => $path,
            ]);
        }
    }
}