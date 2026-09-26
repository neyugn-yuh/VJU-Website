<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Content;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Content $content): RedirectResponse
    {
        abort_unless($content->is_commentable && $content->isPublished(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'website' => ['nullable', 'max:0'], // honeypot: humans never fill it
        ]);

        Comment::create([
            'content_id' => $content->id,
            'name' => strip_tags($data['name']),
            'email' => strtolower($data['email']),
            // Stored as plain text; React renders it escaped.
            'body' => trim(strip_tags($data['body'])),
            'status' => 'pending',
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return back()->with('success', __('public.comment_pending'));
    }
}
