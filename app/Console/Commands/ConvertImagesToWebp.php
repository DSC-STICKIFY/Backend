<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductsModel;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;

class ConvertImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:convert-webp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert all existing product images to WebP format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = ProductsModel::whereNotNull('product_image')->get();
        $manager = new ImageManager(new Driver());
        $count = 0;

        foreach ($products as $product) {
            $currentPath = $product->product_image;

            // Skip if already webp
            if (str_ends_with(strtolower($currentPath), '.webp')) {
                continue;
            }

            if (!Storage::disk('public')->exists($currentPath)) {
                $this->error("File not found: " . $currentPath);
                continue;
            }

            try {
                $fileContents = Storage::disk('public')->get($currentPath);
                
                $image = $manager->decode($fileContents);
                $image->scaleDown(width: 800);
                $encoded = $image->encode(new WebpEncoder(80));

                $filename = uniqid('prod_ migrated_') . '.webp';
                $newPath = 'products/' . $filename;

                Storage::disk('public')->put($newPath, (string) $encoded);
                
                // Delete old file
                Storage::disk('public')->delete($currentPath);

                // Update DB
                $product->update(['product_image' => $newPath]);

                $this->info("Converted: $currentPath -> $newPath");
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to convert $currentPath: " . $e->getMessage());
            }
        }

        $this->info("Successfully converted $count images to WebP.");
    }
}
