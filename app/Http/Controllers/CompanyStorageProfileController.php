<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\CompanyStorageProfile;
use App\Services\CompanyStorageProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class CompanyStorageProfileController extends Controller
{
    public function index(Request $request): View
    {
        $companyId=$this->companyId($request);
        $profiles=CompanyStorageProfile::withoutGlobalScopes()->where('company_id',$companyId)->orderByDesc('is_default')->orderBy('id')->get();
        return view('company_storage_profiles.index',compact('profiles'));
    }
    public function create(Request $request): View { $this->companyId($request); return view('company_storage_profiles.create'); }
    public function store(Request $request): RedirectResponse
    {
        $companyId=$this->companyId($request); $data=$this->validated($request);
        DB::transaction(function()use($companyId,$data,$request){
            if($request->boolean('is_default')) CompanyStorageProfile::withoutGlobalScopes()->where('company_id',$companyId)->update(['is_default'=>false]);
            CompanyStorageProfile::withoutGlobalScopes()->create(array_merge($data,[
                'company_id'=>$companyId,'driver'=>CompanyStorageProfile::DRIVER_S3,
                'is_default'=>$request->boolean('is_default'),'is_active'=>$request->boolean('is_active'),
                'use_path_style_endpoint'=>$request->boolean('use_path_style_endpoint'),
            ]));
        });
        return redirect()->route('company-storage-profiles.index')->with('success','پروفایل ذخیره‌سازی ایجاد شد.');
    }
    public function edit(Request $request,CompanyStorageProfile $companyStorageProfile): View
    { $profile=$this->owned($request,$companyStorageProfile); return view('company_storage_profiles.edit',compact('profile')); }
    public function update(Request $request,CompanyStorageProfile $companyStorageProfile): RedirectResponse
    {
        $profile=$this->owned($request,$companyStorageProfile);$data=$this->validated($request,$profile);
        DB::transaction(function()use($profile,$data,$request){
            if($request->boolean('is_default')) CompanyStorageProfile::withoutGlobalScopes()->where('company_id',$profile->company_id)->whereKeyNot($profile->id)->update(['is_default'=>false]);
            foreach(['access_key','secret_key'] as $secret){if(!array_key_exists($secret,$data)||$data[$secret]===null||$data[$secret]==='')unset($data[$secret]);}
            $profile->update(array_merge($data,['is_default'=>$request->boolean('is_default'),'is_active'=>$request->boolean('is_active'),'use_path_style_endpoint'=>$request->boolean('use_path_style_endpoint')]));
        });
        return redirect()->route('company-storage-profiles.index')->with('success','پروفایل ذخیره‌سازی ویرایش شد.');
    }
    public function makeDefault(Request $request,CompanyStorageProfile $companyStorageProfile): RedirectResponse
    {
        $profile=$this->owned($request,$companyStorageProfile);
        abort_unless($profile->is_active,422,'پروفایل غیرفعال نمی‌تواند پیش‌فرض باشد.');
        DB::transaction(function()use($profile){CompanyStorageProfile::withoutGlobalScopes()->where('company_id',$profile->company_id)->update(['is_default'=>false]);$profile->update(['is_default'=>true]);});
        return back()->with('success','فضای ذخیره‌سازی پیش‌فرض تغییر کرد.');
    }
    public function testConnection(Request $request,CompanyStorageProfile $companyStorageProfile,CompanyStorageProfileService $service): RedirectResponse
    {
        $profile=$this->owned($request,$companyStorageProfile);
        try{$disk=$service->disk($profile);$probe='.asset-system-health/'.bin2hex(random_bytes(8)).'.txt';$disk->put($probe,'ok');$disk->delete($probe);$profile->update(['last_tested_at'=>now(),'last_test_status'=>'success']);return back()->with('success','اتصال و دسترسی نوشتن/حذف با موفقیت تست شد.');}
        catch(Throwable $e){$profile->update(['last_tested_at'=>now(),'last_test_status'=>'failed']);report($e);return back()->withErrors(['storage_profile'=>'تست اتصال ناموفق بود. تنظیمات Endpoint، Bucket و دسترسی‌ها را بررسی کنید.']);}
    }
    private function validated(Request $request,?CompanyStorageProfile $profile=null): array
    {
        return $request->validate([
            'name'=>['required','string','max:150'],'bucket'=>['required','string','max:255'],
            'region'=>['nullable','string','max:100'],'endpoint'=>['nullable','url','max:1000'],'url'=>['nullable','url','max:1000'],
            'root_prefix'=>['nullable','string','max:500','regex:/^[^\\\\]*$/'],
            'access_key'=>[$profile?'nullable':'required','string','max:1000'],'secret_key'=>[$profile?'nullable':'required','string','max:2000'],
            'use_path_style_endpoint'=>['nullable','boolean'],'is_default'=>['nullable','boolean'],'is_active'=>['nullable','boolean'],
        ]);
    }
    private function companyId(Request $request): int
    { $u=$request->user();abort_if($u->isSuperAdmin()||$u->company_id===null,403,'برای مدیریت فضای ذخیره‌سازی وارد حساب یک شرکت شوید.');return (int)$u->company_id; }
    private function owned(Request $request,CompanyStorageProfile $profile): CompanyStorageProfile
    { $companyId=$this->companyId($request);return CompanyStorageProfile::withoutGlobalScopes()->where('company_id',$companyId)->findOrFail($profile->id); }
}