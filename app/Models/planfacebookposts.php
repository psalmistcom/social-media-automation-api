<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class planfacebookposts extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'post_date',
        'image_link',
        'post_id_from_facebook',
        'post_link',
        'error_message',
        'is_published',
        'facebook_page_id'
    ];

    public function facebook_page(): BelongsTo
    {
        return $this->belongsTo(facebookPage::class, 'facebook_page_id');
    }
}
