<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\VerificationController;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\QuoteResource;
use App\Models\AnswerProject;
use App\Models\Company;
use App\Models\Matches;
use App\Models\NoMatches;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Service;
use App\Notifications\UserVerification;
use App\Models\User;
use App\Notifications\CompanyCreatedNotification;
use App\Notifications\CompanyVerifiedNotification;
use App\Notifications\LicenceVerificationNotification;
use App\Notifications\MatchesUserNotification;
use App\Notifications\UserCreatedNotification;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/auth/claim-company', function () {
    return view('Not allowed');
});
Route::get('share/companies/{slug}', [ShareController::class, 'companies']);

Route::get('register/verification', [VerificationController::class, 'verifyEmail'])->name('auth.verify')->middleware('signed');
Route::post('reset-password', [ResetPasswordController::class, 'verify'])->name('auth.change-password')->middleware('signed');

Route::get('/notification', function () {


    // $nomatch = NoMatches::find(144);
    // $user = User::find($nomatch->user_id);
    // $service = Service::withTrashed()->find($nomatch->service_id);
    // $project = Project::find($nomatch->project_id);



    // $openAnswers = AnswerProject::where('project_id', $nomatch->project_id)->whereNull('answer_id')->get();
    //     $project->openAnswers = $openAnswers;
    //     $project->show_contact = $user;



    // $nomatch->company_name = 'company 1';
    // $nomatch->company_phone = '234234234';
    // $nomatch->company_email = 'company1@gmail.com';
    // $nomatch->message = 'Message body' ?? '';
    // $nomatch->save();
    // $project =  new ProjectResource($project);

    // $project->company_name = $nomatch->company_name;
$company = Company::first();
$link = $company->generateClaimUrl();
//remove prefix/api from link and return it
$link = str_replace('/api', '', $link);
return $link;
    return view('mail.company.matches-company-ai', ['project' => Project::first(), 'notifiable' => User::first(), 'link' => 'https://www.thesvnetwork.com/admin/quotes/24']);
    // $user->link = 'localhost:8000/asdasdadsdadasd.com';
    // $match = Matches::all();
    // $service = Service::find(1);
    // $matches = $service->companyServiceZip
    // ->where('zipcode_id', 16281)
    // ->take(3);
    // $matches = $matches->map(function($match) use($user,$service){
    //     return new CompanyResource($match->company);
    // });

    // $matches = Company::all()->take(3);


    // $data = ['matches' => $matches, 'service'=>$service];
    // //  $user->notify(new MatchesUserNotification($matches));
    // return view('mail.invoice.paid', ['matches' => $data]);
    $company = Company::first();
    // $link = config('app.app_url') . '/admin/companies/'. $company->id;
    // $company->link = $link;
    // $user->notify(new LicenceVerificationNotification($company));
});


