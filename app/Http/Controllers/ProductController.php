<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProductController extends Controller
{
    private function getUploadPath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/uploads';
    }

    public function index()
    {
        return Inertia::render('products/Index', [
            'products'   => Product::with('category', 'images')->latest()->paginate(10),
            'categories' => Category::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'colors'      => 'required|array',
            'sizes'       => 'required|array',
            'images'      => 'nullable|array|max:3',
            'images.*'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $product = Product::query()->create([
            'category_id' => $validated['category_id'],
            'name'        => $validated['name'],
            'description' => $validated['description'],
            'price'       => $validated['price'],
            'stock'       => $validated['stock'],
            'colors'      => $validated['colors'],
            'sizes'       => $validated['sizes'],
            'slug'        => $slug,
            'sku'         => 'SKU-' . strtoupper(Str::random(8)),
        ]);

        $this->uploadImages($product, $request->file('images') ?? []);

        return redirect()->route('products.index');
    }

    public function update(Request $request, string $id)
    {
        $product = Product::query()->findOrFail($id);

        $validated = $request->validate([
            'category_id'         => 'required|exists:categories,id',
            'name'                => 'required|string|max:255',
            'description'         => 'required|string',
            'price'               => 'required|numeric|min:0',
            'stock'               => 'required|integer|min:0',
            'colors'              => 'required|array',
            'sizes'               => 'required|array',
            'images'              => 'nullable|array|max:3',
            'images.*'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
            'removed_image_ids'   => 'nullable|array',
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
            'name'        => $validated['name'],
            'description' => $validated['description'],
            'price'       => $validated['price'],
            'stock'       => $validated['stock'],
            'colors'      => $validated['colors'],
            'sizes'       => $validated['sizes'],
            'slug'        => $validated['slug'] ?? $product->slug,
        ]);

        if (!empty($validated['removed_image_ids'])) {
            $uploadPath    = $this->getUploadPath();
            $removedImages = $product->images()->whereIn('id', $validated['removed_image_ids'])->get();
            foreach ($removedImages as $image) {
                $imagePath = $uploadPath . '/' . basename($image->path);
                if (file_exists($imagePath)) unlink($imagePath);
            }
            $product->images()->whereIn('id', $validated['removed_image_ids'])->delete();
        }

        $this->uploadImages($product, $request->file('images') ?? []);

        return redirect()->route('products.index');
    }

    public function destroy(string $id)
    {
        $product    = Product::query()->findOrFail($id);
        $uploadPath = $this->getUploadPath();

        foreach ($product->images as $image) {
            $imagePath = $uploadPath . '/' . basename($image->path);
            if (file_exists($imagePath)) unlink($imagePath);
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

        $manager = new ImageManager(new Driver());

        foreach ($images as $image) {
            if (!$image) continue;

            $filename = time() . '_' . Str::random(20) . '.webp';

            $manager->read($image->getRealPath())
                ->scaleDown(width: 600)
                ->toWebp(quality: 60)
                ->save($uploadPath . '/' . $filename);

            $product->images()->create([
                'path' => 'uploads/' . $filename,
            ]);
        }
    }
}