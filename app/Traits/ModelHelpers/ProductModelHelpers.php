<?php
namespace App\Traits\ModelHelpers;

use Illuminate\Support\Str;

trait ProductModelHelpers
{
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

    public function getImageUrl(): string
    {
        return asset('storage/' . $this->image_path);
    }

    public function getVideoUrl(): string
    {
        return asset('storage/' . $this->video_path);
    }

    public function hasImage(): bool
    {
        return !empty($this->image_path);
    }

    public function hasVideo(): bool
    {
        return !empty($this->video_path);
    }

    public function generateSlug(): string
    {
        return Str::slug($this->title); 
    }
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function setImagePathBeforeSave($image)
    {
        $this->image_path = $image->store('images', 'public');
    }
}
