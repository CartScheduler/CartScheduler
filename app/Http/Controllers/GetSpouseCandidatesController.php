<?php

namespace App\Http\Controllers;

use App\Data\SpouseAdminData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GetSpouseCandidatesController extends Controller
{
    /**
     * Users of the opposite gender who are free to be attached as a spouse. Anyone already
     * attached to another user is excluded, but the current spouse of `user_id` is kept.
     *
     * @return Collection<int, SpouseAdminData>
     */
    public function __invoke(Request $request): Collection
    {
        $request->validate([
            'gender' => ['required', 'string', 'in:male,female'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->integer('user_id') ?: null;
        $oppositeGender = $request->string('gender')->toString() === 'male' ? 'female' : 'male';

        $candidates = User::query()
            ->select(['id', 'name'])
            ->where('gender', $oppositeGender)
            ->when($userId, fn($query) => $query->where('id', '!=', $userId))
            ->whereNotIn('id', User::query()
                ->select('spouse_id')
                ->whereNotNull('spouse_id')
                ->when($userId, fn($query) => $query->where('id', '!=', $userId)))
            ->orderBy('name')
            ->get();

        return SpouseAdminData::collect($candidates);
    }
}
