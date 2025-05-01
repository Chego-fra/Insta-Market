<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessProductMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $product;
    public $image;
    public $video;

    public function __construct(Product $product, $image = null, $video = null)
    {
        $this->product = $product;
        $this->image = $image;
        $this->video = $video;
    }

    public function handle()
    {
        // ✅ Process image if provided
        if ($this->image) {
            $extension = strtolower($this->image->getClientOriginalExtension());
            $realPath = $this->image->getRealPath();
            $imageResource = false;

            // 🔄 Create image resource based on MIME
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $imageResource = imagecreatefromjpeg($realPath);
                    break;
                case 'png':
                    $imageResource = imagecreatefrompng($realPath);
                    break;
                case 'webp':
                    $imageResource = imagecreatefromwebp($realPath);
                    break;
                default:
                    // ❌ Unsupported format, exit silently
                    return;
            }

            if (!$imageResource) {
                return; // Invalid/corrupt image
            }

            // 🖼️ Resize to 800x600
            $resizedImage = imagescale($imageResource, 800, 600);

            // 📂 Save as compressed JPEG
            $path = 'images/' . uniqid() . '.jpg';
            imagejpeg($resizedImage, storage_path('app/public/' . $path), 75);

            // 🧹 Free memory
            imagedestroy($imageResource);
            imagedestroy($resizedImage);

            // 💾 Update DB
            $this->product->update(['image_path' => $path]);
        }

        // 🎞️ Process video if provided
        if ($this->video) {
            $videoPath = $this->video->store('videos', 'public');
            $this->product->update(['video_path' => $videoPath]);
        }
    }
}
