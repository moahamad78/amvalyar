<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Location;
use App\Models\Site;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Services\StocktakeCountingService;
use App\Services\StocktakeFinalizationService;
use App\Services\StocktakeReconciliationService;
use App\Services\StocktakeStartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StocktakeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Stocktake::withoutGlobalScopes()->with(['site','department','location'])->latest('id');
        if (! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }
        return view('stocktakes.index', ['stocktakes' => $query->paginate(20)]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->companyId($request);
        return view('stocktakes.create', [
            'sites' => Site::withoutGlobalScopes()->where('company_id',$companyId)->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
            'departments' => Department::withoutGlobalScopes()->where('company_id',$companyId)->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::withoutGlobalScopes()->where('company_id',$companyId)->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'title'=>['required','string','max:255'],
            'scope_type'=>['required','in:company,site,department,location'],
            'site_id'=>['nullable','integer'],
            'department_id'=>['nullable','integer'],
            'location_id'=>['nullable','integer'],
            'planned_date'=>['nullable','date'],
            'notes'=>['nullable','string','max:4000'],
        ]);
        $companyId=$this->companyId($request);
        foreach (['site_id'=>Site::class,'department_id'=>Department::class,'location_id'=>Location::class] as $key=>$model) {
            if (!empty($data[$key])) {
                abort_unless($model::withoutGlobalScopes()->where('company_id',$companyId)->whereKey((int)$data[$key])->exists(),422);
            }
        }
        $required=['site'=>'site_id','department'=>'department_id','location'=>'location_id'];
        if(isset($required[$data['scope_type']]) && empty($data[$required[$data['scope_type']]])){
            return back()->withInput()->withErrors([$required[$data['scope_type']]=>'انتخاب محدوده الزامی است.']);
        }
        $stocktake=Stocktake::withoutGlobalScopes()->create([
            'company_id'=>$companyId,
            'code'=>'STK-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6)),
            'title'=>$data['title'],
            'scope_type'=>$data['scope_type'],
            'site_id'=>$data['scope_type']==='site' ? ($data['site_id']??null) : null,
            'department_id'=>$data['scope_type']==='department' ? ($data['department_id']??null) : null,
            'location_id'=>$data['scope_type']==='location' ? ($data['location_id']??null) : null,
            'status'=>Stocktake::STATUS_DRAFT,
            'planned_date'=>$data['planned_date']??null,
            'created_by'=>$request->user()->id,
            'notes'=>$data['notes']??null,
        ]);
        return redirect()->route('stocktakes.show',$stocktake)->with('success','انبارگردانی ایجاد شد.');
    }

    public function show(Request $request, Stocktake $stocktake): View
    {
        $stocktake=$this->owned($request,$stocktake);
        $stocktake->load(['site','department','location','creator','completer']);
        $items=StocktakeItem::withoutGlobalScopes()
            ->where('stocktake_id',$stocktake->id)->where('company_id',$stocktake->company_id)
            ->with(['asset','counter'])->orderBy('result_status')->orderBy('id')->paginate(50);
        $counts=StocktakeItem::withoutGlobalScopes()->where('stocktake_id',$stocktake->id)
            ->selectRaw('result_status, COUNT(*) as aggregate')->groupBy('result_status')->pluck('aggregate','result_status');
        return view('stocktakes.show',compact('stocktake','items','counts'));
    }

    public function start(Request $request, Stocktake $stocktake, StocktakeStartService $service): RedirectResponse
    {
        $stocktake=$this->owned($request,$stocktake); $service->start($stocktake,$request->user());
        return back()->with('success','انبارگردانی شروع و تصویر مبنا ثبت شد.');
    }

    public function observe(Request $request, Stocktake $stocktake, StocktakeCountingService $service): RedirectResponse
    {
        $stocktake=$this->owned($request,$stocktake);
        $data=$request->validate([
            'asset_code'=>['required','string','max:255'],
            'damaged'=>['nullable','boolean'],
            'notes'=>['nullable','string','max:4000'],
        ]);
        $asset=Asset::withoutGlobalScopes()->where('company_id',$stocktake->company_id)
            ->where(function($q)use($data){$q->where('asset_code',$data['asset_code'])->orWhere('inventory_code',$data['asset_code']);})
            ->firstOrFail();
        $item=$service->observe($stocktake,$asset,$request->user(),[
            'damaged'=>(bool)($data['damaged']??false),'notes'=>$data['notes']??null,
        ]);
        return back()->with('success','شمارش ثبت شد: '.$item->result_status);
    }

    public function missing(Request $request, Stocktake $stocktake, StocktakeItem $item, StocktakeCountingService $service): RedirectResponse
    {
        $stocktake=$this->owned($request,$stocktake);
        abort_unless((int)$item->stocktake_id===(int)$stocktake->id && (int)$item->company_id===(int)$stocktake->company_id,404);
        $data=$request->validate(['notes'=>['nullable','string','max:4000']]);
        $asset=Asset::withoutGlobalScopes()->where('company_id',$stocktake->company_id)->findOrFail($item->asset_id);
        $service->markMissing($stocktake,$asset,$request->user(),$data['notes']??null);
        return back()->with('success','مال به‌عنوان یافت‌نشده ثبت شد.');
    }

    public function recount(Request $request, Stocktake $stocktake, StocktakeFinalizationService $service): RedirectResponse
    {
        $stocktake=$this->owned($request,$stocktake); $service->requestRecount($stocktake,$request->user());
        return back()->with('success','اقلام مغایر وارد بازشماری شدند.');
    }

    public function complete(Request $request, Stocktake $stocktake, StocktakeFinalizationService $service): RedirectResponse
    {
        $stocktake=$this->owned($request,$stocktake); $service->complete($stocktake,$request->user());
        return back()->with('success','انبارگردانی نهایی شد.');
    }


    public function reconciliation(Request $request, Stocktake $stocktake): View
    {
        $stocktake = $this->owned($request, $stocktake);
        abort_unless($stocktake->status === Stocktake::STATUS_COMPLETED, 404);

        $items = StocktakeItem::withoutGlobalScopes()
            ->where('stocktake_id', $stocktake->id)
            ->where('company_id', $stocktake->company_id)
            ->whereIn('result_status', [
                StocktakeItem::RESULT_MISSING,
                StocktakeItem::RESULT_MISPLACED,
                StocktakeItem::RESULT_CUSTODY_MISMATCH,
                StocktakeItem::RESULT_DAMAGED,
            ])
            ->with(['asset', 'counter', 'reconciler'])
            ->orderByRaw("CASE WHEN reconciliation_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('result_status')
            ->orderBy('id')
            ->paginate(30);

        $counts = StocktakeItem::withoutGlobalScopes()
            ->where('stocktake_id', $stocktake->id)
            ->where('company_id', $stocktake->company_id)
            ->whereIn('result_status', [
                StocktakeItem::RESULT_MISSING,
                StocktakeItem::RESULT_MISPLACED,
                StocktakeItem::RESULT_CUSTODY_MISMATCH,
                StocktakeItem::RESULT_DAMAGED,
            ])
            ->selectRaw('reconciliation_status, COUNT(*) as aggregate')
            ->groupBy('reconciliation_status')
            ->pluck('aggregate', 'reconciliation_status');

        return view('stocktakes.reconciliation', compact('stocktake', 'items', 'counts'));
    }

    public function applyReconciliation(
        Request $request,
        Stocktake $stocktake,
        StocktakeItem $item,
        StocktakeReconciliationService $service
    ): RedirectResponse {
        $stocktake = $this->owned($request, $stocktake);
        $this->ownedItem($stocktake, $item);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:4000']]);

        $service->applyObserved($item, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Observed stocktake state was applied to the canonical asset record.');
    }

    public function resolveReconciliation(
        Request $request,
        Stocktake $stocktake,
        StocktakeItem $item,
        StocktakeReconciliationService $service
    ): RedirectResponse {
        $stocktake = $this->owned($request, $stocktake);
        $this->ownedItem($stocktake, $item);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:4000']]);

        $service->resolveWithoutChange($item, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Stocktake discrepancy was resolved without changing the canonical asset record.');
    }

    private function ownedItem(Stocktake $stocktake, StocktakeItem $item): StocktakeItem
    {
        return StocktakeItem::withoutGlobalScopes()
            ->where('company_id', $stocktake->company_id)
            ->where('stocktake_id', $stocktake->id)
            ->findOrFail($item->id);
    }
    private function companyId(Request $request): int
    {
        $user=$request->user();
        abort_if($user->isSuperAdmin() || $user->company_id===null,403,'برای ایجاد انبارگردانی وارد حساب یک شرکت شوید.');
        return (int)$user->company_id;
    }

    private function owned(Request $request, Stocktake $stocktake): Stocktake
    {
        $fresh=Stocktake::withoutGlobalScopes()->findOrFail($stocktake->id);
        if(! $request->user()->isSuperAdmin()){
            abort_unless((int)$fresh->company_id===(int)$request->user()->company_id,404);
        }
        return $fresh;
    }
}