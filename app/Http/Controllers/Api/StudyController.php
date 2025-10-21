<!-- 
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudySession;
use Illuminate\Support\Facades\Validator;

class StudyController extends Controller
{
    public function startSession(Request $request, $deckId = null)
    {
        $session = StudySession::create([
            'user_id' => $request->user()->id,
            'deck_id' => $deckId,
            'started_at' => now(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'session_id' => $session->id,
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function endSession(Request $request, StudySession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 403, 'Không có quyền');
        
        $data = Validator::make($request->all(), [
            'cards_studied' => ['required', 'integer'],
            'correct_count' => ['required', 'integer'],
        ])->validate();

        $session->update([
            'cards_studied' => $data['cards_studied'],
            'correct_count' => $data['correct_count'],
            'ended_at' => now(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'summary' => $session,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
} -->