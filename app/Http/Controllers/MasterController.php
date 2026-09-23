<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;

class MasterController extends Controller
{
	private function getMaster(Request $request)
	{
		return $request->attributes->get('current_master');
	}

	public function attach(Request $request, ReferralService $referrals)
	{
		$master = $this->getMaster($request);

		if ($master === null) {
			return response()->json(['message' => 'Мастер не найден'], 401);
		}
		$input = $request->input('code');

		$code = is_string($input) ? trim($input) : '';

		if ($code === '') {
			return response()->json(['message' => 'Поле code нужно'], 422);
		}

		$referral = Master::where('referral_code', $code)->first();

		if ($referral === null) {
			return response()->json(['message' => 'Код не найден'], 404);
		}
		if ($master->id === $referral->id) {
			return response()->json(['message' => 'Нельзя привязать к себе'], 422);
		}

		$referral = $referrals ->registerReferral($master, $code);

		if ($referral->referred_master_id !== $referral->id) {
			return response()->json([
				'message' => 'Мастер уже закреплён за другим владельцем кода',
				'referrer' => [
					'id' => $referral->referrerMaster->id,
					'name' => $referral->referrerMaster->name,
				],
			], 409);
		}
		return response()->json([
			'message' => $referral->wasRecentlyCreated ? 'Мастер закреплён' : 'Мастер уже был закреплён ранее',
			'referrer' => ['id' => $referral->id, 'name' => $referral->name],
			'attached_at' => $referral->created_at->toISOString(),
		], $referral->wasRecentlyCreated ? 201 : 200);
	}

	public function myReferral(Request $request, ReferralEarning $referralEarning)
	{
		$master = $this->getMaster($request);

		if ($master === null) {
			return response()->json([
				'message' => 'Не удалось определить мастера: передайте корректный заголовок X-Master-Id',
			], 401);
		}

		$referrals = $master->referrals()->with('referredMaster')->get();

		// Начисления по каждому рефералу, сгруппированные по referral_id.
		$earningsByReferral = $master->referralEarnings()->get()->groupBy('referral_id');

		return response()->json([
			'referrals' => $referrals->map(fn(Referral $referral) => [
				'id' => $referral->referred_master_id,
				'name' => $referral->referredMaster->name,
				'attached_at' => $referral->created_at->toISOString(),
				// Засчитан = по нему уже начислено вознаграждение
				'counted' => $referral->status === Referral::STATUS_REWARDED,
				'earned' => (int)$earningsByReferral->get($referral->id, collect())->sum('amount'),
			]),
		]);

	}

	public function earnings(Request $request)
	{
		$master = $this->getMaster($request);
		if ($master === null) {
			return response()->json([
				'message' => 'Не удалось определить мастера: передайте корректный заголовок X-Master-Id',
			], 401);
		}

		$earnings = $master->referralEarnings()->get();

		return response()->json([
			'total' => (int) $earnings->sum('amount'),
			'pending' => (int) $earnings->where('status', ReferralEarning::STATUS_PENDING)->sum('amount'),
			'paid' => (int) $earnings->where('status', ReferralEarning::STATUS_PAID)->sum('amount'),
			'counted_referrals' => $master->referrals()->where('status', Referral::STATUS_REWARDED)->count(),
		]);
	}
}