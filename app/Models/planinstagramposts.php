<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class planinstagramposts extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'post_date',
        'image_link',
        'post_id_from_instagram',
        'post_link',
        'error_message',
        'is_published',
        'instagram_page_id'
    ];

    public function instagram_page(): BelongsTo
    {
        return $this->belongsTo(instagramPage::class, 'instagram_page_id');
    }
}
