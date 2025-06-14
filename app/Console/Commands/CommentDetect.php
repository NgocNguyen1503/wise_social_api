<?php

namespace App\Console\Commands;

use App\Models\Comment;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class CommentDetect
 * @package App\Console\Commands
 *
 * This command is responsible for detecting violent comments by sending them
 * to an external detection service and updating user violation records.
 */
class CommentDetect extends Command
{
    /**
     * The URL of the external comment detection service.
     */
    const DETECT_URL = 'http://127.0.0.1:5000/detect';

    /**
     * The number of comments to process in each batch.
     */
    const TAKE_RECORD = 100;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:comment-detect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detects and logs violent comments using an external service.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Initialize Guzzle HTTP client for making API requests
        $client = new Client();

        // Retrieve the last processed comment ID from the violence logs table
        $violenceLogs = DB::table('violence_logs')->first();
        $commentId = 1; // Default starting ID

        // If a log exists, update the starting comment ID
        if (!is_null($violenceLogs)) {
            if (isset($violenceLogs->comment_id) && $violenceLogs->comment_id > 0) {
                $commentId = $violenceLogs->comment_id;
            }
        }

        // Get comments in batches, starting from the last processed ID
        $comments = Comment::select('id', 'comment', 'user_id')
            ->orderBy('id', 'ASC')
            ->where('id', '>', $commentId)
            ->take(self::TAKE_RECORD)
            ->get();

        // Store the ID of the last processed comment in the current batch
        $recordIndex = $commentId;

        // If no new comments are found, exit
        if ($comments->isEmpty()) { // Use isEmpty() for collections
            Log::info("Done processing comments.");
            return null;
        }

        // Iterate through each comment to send for detection
        foreach ($comments as $comment) {
            // Send POST request to the detection service with the comment text
            $detect = $client->request('POST', self::DETECT_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'text' => $comment->comment,
                ],
            ]);

            // Decode the JSON response from the detection service
            $response = json_decode($detect->getBody(), true);

            // Check if the comment is flagged as 'bad'
            if ($response['is_bad'] == true || $response['is_bad'] == "true") {
                // Retrieve existing warning for the user
                $detectWarning = DB::table('violence_warnings')
                    ->where('user_id', $comment->user_id)
                    ->first();

                $warning = 0;
                // Increment warning count
                if (empty($detectWarning)) {
                    $warning = 1; // First infringement
                } else {
                    $warning = $detectWarning->infringe + 1; // Increment existing infringement count
                }

                // Update or insert the user's violence warning record
                DB::table('violence_warnings')->updateOrInsert(
                    [
                        'user_id' => $comment->user_id
                    ],
                    [
                        'user_id' => $comment->user_id,
                        'infringe' => $warning
                    ],
                );
            }
            // Update the recordIndex to the ID of the current comment being processed
            $recordIndex = $comment->id;
        }

        // Update the violence_logs table with the ID of the last processed comment
        DB::table('violence_logs')->updateOrInsert(
            ['id' => 1],
            ['comment_id' => $recordIndex],
        );

        // Output status message
        Log::info("Detect comment " . $commentId . " to " . $recordIndex . " done.");
        return null;
    }
}
