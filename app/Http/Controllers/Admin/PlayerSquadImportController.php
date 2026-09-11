<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LiveFootballApiException;
use App\Exceptions\PlayerSquadSyncException;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Team;
use App\Services\Football\PlayerSquadSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PlayerSquadImportController extends Controller
{
    public function create(Request $request, PlayerSquadSyncService $sync): View
    {
        $this->authorize('create', Player::class);
        $preview = null;
        $previewError = null;
        $teamId = $request->integer('team_id');
        $token = $request->string('token')->toString();

        if ($teamId > 0 && $token !== '') {
            try {
                $team = Team::query()->findOrFail($teamId);
                $preview = $sync->loadPreview($request->user(), $team, $token);
            } catch (PlayerSquadSyncException) {
                $previewError = 'Kadro önizlemesi geçersiz veya süresi dolmuş. Lütfen kadroyu yeniden getirin.';
            }
        }

        return view('admin.players.import', [
            'teams' => $sync->eligibleTeams(),
            'preview' => $preview,
            'previewError' => $previewError,
            'selectedTeamId' => $preview ? $preview['team']->id : $teamId,
        ]);
    }

    public function preview(Request $request, PlayerSquadSyncService $sync): RedirectResponse
    {
        $this->authorize('create', Player::class);
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
        ]);
        $team = Team::query()->findOrFail($data['team_id']);

        try {
            $preview = $sync->createPreview($request->user(), $team);
        } catch (LiveFootballApiException|PlayerSquadSyncException $exception) {
            return back()->withInput()->withErrors(['team_id' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'team_id' => 'Football API şu anda yanıt vermiyor. Veritabanında herhangi bir değişiklik yapılmadı.',
            ]);
        }

        return redirect()->route('admin.players.import.create', [
            'team_id' => $team->id,
            'token' => $preview['token'],
        ]);
    }

    public function apply(Request $request, PlayerSquadSyncService $sync): RedirectResponse
    {
        $this->authorize('create', Player::class);
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
            'players' => ['required', 'array', 'max:100'],
            'players.*' => ['array'],
            'players.*.provider_player_id' => ['required', 'string', 'max:100'],
            'players.*.selected' => ['nullable', 'boolean'],
            'players.*.action' => ['nullable', 'string', 'in:sync,create,map'],
            'players.*.local_player_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $team = Team::query()->findOrFail($data['team_id']);
        $redirectParameters = ['team_id' => $team->id, 'token' => $data['token']];

        try {
            $summary = $sync->apply($request->user(), $team, $data['token'], $data['players']);
        } catch (PlayerSquadSyncException $exception) {
            return redirect()->route('admin.players.import.create', $redirectParameters)
                ->withInput()
                ->withErrors(['import' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.players.import.create', $redirectParameters)
                ->withInput()
                ->withErrors([
                    'import' => 'Kadro aktarımı tamamlanamadı. Veritabanında herhangi bir değişiklik yapılmadı.',
                ]);
        }

        $message = sprintf(
            '%d oyuncu işlendi: %d yeni oluşturuldu, %d güncellendi, %d mevcut oyuncuyla eşleştirildi, %d hata.',
            $summary['processed'],
            $summary['created'],
            $summary['updated'],
            $summary['mapped'],
            $summary['errors'],
        );

        return redirect()->route('admin.players.index')->with('success', $message);
    }
}
