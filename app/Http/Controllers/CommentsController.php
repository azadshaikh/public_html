<?php

namespace App\Http\Controllers;

use App\Models\Comments;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentsController extends Controller
{
    use ActivityTrait;

    protected string $activityLogModule = 'Comments';

    protected string $activityEntityAttribute = 'body';

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string'],
            'commentable_type' => ['required', 'string'],
            'commentable_id' => ['required', 'integer'],
        ], [
            'body.required' => 'The comment field is required.',
            'commentable_type.required' => 'The commentable type field is required.',
            'commentable_id.required' => 'The commentable id field is required.',
        ]);

        DB::beginTransaction();
        try {
            $input_arr = [
                'body' => $request->body,
                'commentable_type' => $request->commentable_type,
                'commentable_id' => $request->commentable_id,
                'user_id' => auth()->user()->id,
            ];

            $comment = Comments::query()->create($input_arr);
            DB::commit();

            $this->logCreated($comment, 'Comment created successfully.', [
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
            ]);

            return $this->successResponse($comment, 'Comment created successfully.');
        } catch (Exception) {
            DB::rollBack();

            return $this->errorResponse('Error creating comment.');
        }
    }

    private function successResponse($comment, string $message): JsonResponse
    {
        return response()->json([
            'status' => '1',
            'message' => $message,
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at->format('M d, Y H:i'),
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'avatar' => $comment->user->avatar_image,
                ],
            ],
        ], 200);
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => '0',
            'message' => $message,
        ], 400);
    }
}
