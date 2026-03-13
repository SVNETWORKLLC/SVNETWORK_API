<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AnswerProjectController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\GeoipController;
use App\Http\Controllers\MauticController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\RenewPasswordController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionTypeController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationCodeController;
use App\Http\Controllers\ZipcodeController;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
//agrega middleware web primero

Route::middleware(['web', 'auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// AUTH REGISTER + LOGIN
Route::post('/mautic', [MauticController::class, 'createContact']);
Route::get('/auth/user', [UserController::class, 'show'])->middleware(['web', 'auth:sanctum']);
Route::get('/auth/user/check-email/{email}', [UserController::class, 'emailExist']);
Route::post('/check/robot', [UserController::class, 'checkRobot']);
Route::post('/auth/register/company', [RegisterController::class, 'registerCompany']);
Route::post('/auth/register', [RegisterController::class, 'register'])->middleware('web');
Route::post('/auth/google/token', [RegisterController::class, 'googleToken']);
Route::post('/auth/register-guess', [UserController::class, 'storeGuess']);
Route::post('/auth/register/google', [RegisterController::class, 'registerGoogle'])->middleware('web');
Route::post('/auth/register/google/reviews', [RegisterController::class, 'registerGoogleReviews']);
Route::post('/auth/login', [LoginController::class, 'login'])->middleware('web');
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('web');
Route::post('/auth/register/verification', [VerificationController::class, 'verifyEmail'])->middleware('signed');
Route::post('/auth/email/verify/resend/{email}', [VerificationController::class, 'resend']);
Route::post('/auth/renew-password/send', [RenewPasswordController::class, 'send']);

// USER PRIVATE
Route::post('/user/password', [UserController::class, 'updatePassword'])->middleware(['web', 'auth:sanctum']);
Route::get('/user/companies', [UserController::class, 'company'])->middleware(['web', 'auth:sanctum']);
Route::post('/user/companies/refund', [UserController::class, 'requestRefund'])->middleware(['web', 'auth:sanctum']);
Route::get('/user/projects/{project}', [UserController::class, 'showProject']);
Route::get('/user/quotes/{quote}', [UserController::class, 'showQuote']);
Route::get('/user/projects', [UserController::class, 'projects']);
Route::post('/user/image', [UserController::class, 'storeImageAuthUser'])->middleware(['web', 'auth:sanctum']);
Route::put('/user', [UserController::class, 'update'])->middleware(['web', 'auth:sanctum']);
Route::post('/user/fcm-token', [UserController::class, 'saveFcmToken'])->middleware(['web', 'auth:sanctum']);
//SYSTEM DATA

Route::get('/system/geoip/{ip?}', [GeoipController::class, 'show']);
Route::get('/system/states/{iso}', [StateController::class, 'show']);
Route::get('/system/states', [StateController::class, 'list']);
Route::get('/system/categories', [CategoryController::class, 'list']);
Route::get('/system/categories/{slug}/services-list', [CategoryController::class, 'getServicesListBySlug']);
Route::get('/system/categories/{slug}/services', [CategoryController::class, 'getServicesBySlug']);
Route::get('/system/categories/{slug}', [CategoryController::class, 'getBySlug']);
Route::get('/system/categories/{slug}/companies', [CategoryController::class, 'getCompaniesByCategory']);
Route::post('/system/categories', [CategoryController::class, 'store']);
Route::post('/system/services', [ServiceController::class, 'store']);
Route::get('/system/services', [ServiceController::class, 'list']);
Route::get('/system/companies/new-added', [CompanyController::class, 'newAddedCompanies']);
Route::get('/system/services/new-added', [ServiceController::class, 'newAddedServices']);
Route::get('/system/services/top10', [ServiceController::class, 'top10']);
Route::get('/system/services/{service}', [ServiceController::class, 'showPublic']);
Route::get('/system/zipcode/{zipcode}', [ZipcodeController::class, 'show']);
Route::get('/system/zipcode', [ZipcodeController::class, 'list']);

//SEARCH
Route::post('/search/custom/{noMatches}', [SearchController::class, 'searchCustom']);
Route::post('/search/companies', [SearchController::class, 'searchCompanies']);
Route::post('/search/form', [SearchController::class, 'searchForm']);
Route::post('/search', [SearchController::class, 'search']);
Route::post('/search/ai', [SearchController::class, 'searchAi']);

//PAYMENTS
Route::get('/payments/retrieve/session/{id}', [PaymentController::class, 'retrieveSession'])->middleware(['web', 'auth:sanctum']);
Route::get('/payments/retrieve/setup/{id}', [PaymentController::class, 'retrieveIntent'])->middleware(['web', 'auth:sanctum']);
Route::post('/payments/checkout', [PaymentController::class, 'checkout'])->middleware(['web', 'auth:sanctum']);
Route::post('/payments/custom', [PaymentController::class, 'customCard'])->middleware(['web', 'auth:sanctum']);
Route::post('/payments/customer', [PaymentController::class, 'payment'])->middleware(['web', 'auth:sanctum']);
Route::post('/payments-methods/card', [PaymentMethodController::class, 'storeCard'])->middleware(['web', 'auth:sanctum']);
Route::post('/payments/methods/default', [PaymentMethodController::class, 'setDefaultMethod'])->middleware(['web', 'auth:sanctum']);
Route::delete('/payments-methods/card/{id}', [PaymentMethodController::class, 'deleteCard'])->middleware(['web', 'auth:sanctum']);
Route::post('/payments/methods/last-default', [PaymentMethodController::class, 'setLastMethodPaymentAsDefault'])->middleware(['web', 'auth:sanctum']);
Route::get('/payments/methods', [PaymentController::class, 'getMethodCard'])->middleware(['web', 'auth:sanctum']);
Route::get('/customer', [PaymentController::class, 'getCustomer'])->middleware(['web', 'auth:sanctum']);
Route::get('/user/payments', [PaymentController::class, 'getCharges'])->middleware(['web', 'auth:sanctum']);
Route::get('/user/payments/week', [PaymentController::class, 'totalWeek'])->middleware(['web', 'auth:sanctum']);
Route::get('/payments/methods/{setup}', [PaymentController::class, 'addPaymentMethod'])->middleware(['web', 'auth:sanctum']);


//COMPANIES
Route::get('/companies/config/{company}', [CompanyController::class, 'getConfiguration'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/remove', [CompanyController::class, 'detachService'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services', [CompanyController::class, 'addService'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/states/copy', [CompanyController::class, 'copyStates'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes', [ServiceController::class, 'zipcodesByRegion'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/county', [ServiceController::class, 'zipcodesByCounty'])->middleware(['web', 'auth:sanctum']);
Route::delete('/companies/services/county', [ServiceController::class, 'deleteZipcodesByCounty'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/states/remove', [CompanyController::class, 'removeState'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/states', [CompanyController::class, 'storeState'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes/update', [ServiceController::class, 'updateZipcodes'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes/add', [ServiceController::class, 'addZipcode'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes/county/add', [ServiceController::class, 'addZipcodesByCounty'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes/county/remove', [ServiceController::class, 'removeZipcodesByCounty'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes/delete', [ServiceController::class, 'removeZipcode'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes/all', [ServiceController::class, 'selectAllState'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/zipcodes/remove', [ServiceController::class, 'removeAllState'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/services/pause', [ServiceController::class, 'pause'])->middleware(['web', 'auth:sanctum']);
Route::get('/companies/services/{slug}/{company_id}', [CompanyController::class, 'getService'])->middleware(['web', 'auth:sanctum']);
Route::get('/companies/{company}/projects', [CompanyController::class, 'projects']);
Route::post('/companies', [CompanyController::class, 'storeFromRegister']);
Route::put('/companies/{company}', [CompanyController::class, 'update'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/{company}/logo', [CompanyController::class, 'storeLogo'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/{company}/cover', [CompanyController::class, 'storeCover'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/{company}/images', [CompanyController::class, 'storeImages'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/{company}/documents', [CompanyController::class, 'storeDocument'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/{company}/licence', [CompanyController::class, 'storeLicence'])->middleware(['web', 'auth:sanctum']);
Route::delete('/companies/images/{image}', [CompanyController::class, 'deleteImage'])->middleware(['web', 'auth:sanctum']);
Route::post('/companies/{company}/video', [CompanyController::class, 'storeVideo'])->middleware(['web', 'auth:sanctum']);
Route::get('/companies/{company}/reviews', [CompanyController::class, 'reviews'])->middleware(['web', 'auth:sanctum']);
Route::get('/companies/progress', [CompanyController::class, 'getProgress'])->middleware(['web', 'auth:sanctum']);
Route::get('/companies/{slug}', [CompanyController::class, 'showBySlug']);

//PROJECTS
Route::post('/projects/images', [ProjectController::class, 'storeImage'])->middleware(['web', 'auth:sanctum']);
Route::post('/quotes/images', [QuoteController::class, 'storeImage'])->middleware(['web', 'auth:sanctum']);
Route::post('/projects', [ProjectController::class, 'store'])->middleware(['web', 'auth:sanctum']);
Route::post('/quotes/send', [QuoteController::class, 'send'])->middleware(['web', 'auth:sanctum']);
Route::post('/quotes', [QuoteController::class, 'store'])->middleware(['web']);
Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware(['web', 'auth:sanctum']);
Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->middleware(['web', 'auth:sanctum']);
Route::get('/matches/{match}/contact/check', [ProjectController::class, 'showContactCheck'])->middleware(['web', 'auth:sanctum']);
Route::get('/matches/{match}/contact', [ProjectController::class, 'showContact'])->middleware(['web', 'auth:sanctum']);

//ANSWERS
Route::post('/answers', [AnswerProjectController::class, 'store'])->middleware(['web', 'auth:sanctum']);


//REVIEWS
Route::post('/reviews', [ReviewController::class, 'store'])->middleware(['web', 'auth:sanctum']);
Route::post('/reviews/reply', [ReviewController::class, 'reply'])->middleware(['web', 'auth:sanctum']);
Route::put('/reviews/reply/{reviewReplay}', [ReviewController::class, 'updateReply'])->middleware(['web', 'auth:sanctum']);
Route::put('/reviews/{review}', [ReviewController::class, 'update'])->middleware(['web', 'auth:sanctum']);
Route::delete('/reviews/{review}', [ReviewController::class, 'delete'])->middleware(['web', 'auth:sanctum']);
Route::get('/reviews', [ReviewController::class, 'list']);
Route::post('/reviews/{review}/report', [ReviewController::class, 'report'])->middleware(['web', 'auth:sanctum']);

//DASHBOARD ADMIN

Route::group(['middleware' => ['web', 'auth:sanctum', 'isAdmin']], function () {
    Route::post('/admin/companies/{company}/logo', [CompanyController::class, 'storeLogoAdmin']);
    Route::post('/admin/companies', [CompanyController::class, 'store']);
    Route::post('/admin/companies/verify', [CompanyController::class, 'verify']);
    Route::post('/admin/companies/verify/licence', [CompanyController::class, 'verifyLicence']);
    Route::post('/admin/companies/verify/insurance', [CompanyController::class, 'verifyInsurance']);
    Route::post('/admin/companies/user', [CompanyController::class, 'addUser']);
    Route::put('/admin/companies/{company}', [CompanyController::class, 'updateAdmin']);
    Route::delete('/admin/companies/{company}', [CompanyController::class, 'destroy']);
    Route::get('/admin/companies/unverified', [CompanyController::class, 'listUnverified']);
    Route::get('/admin/companies', [CompanyController::class, 'list']);
    Route::get('/admin/companies/{company}', [CompanyController::class, 'show']);
    Route::post('/admin/users/{user}/image', [UserController::class, 'storeImage']);
    Route::post('/admin/companies/{company}/video', [CompanyController::class, 'storeVideo']);
    Route::get('/admin/users/pro', [UserController::class, 'listPro']);
    Route::get('/admin/users', [UserController::class, 'list']);
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::post('/admin/services/add', [ServiceController::class, 'store']);
    Route::post('/admin/services', [ServiceController::class, 'store']);
    Route::post('/admin/services/{service}/images', [ServiceController::class, 'storeImage']);
    Route::delete('/admin/services/{service}', [ServiceController::class, 'destroy']);
    Route::get('/admin/services/prices', [ServiceController::class, 'prices']);
    Route::put('/admin/services/{service}', [ServiceController::class, 'update']);
    Route::get('/admin/services/{service}', [ServiceController::class, 'show']);
    Route::post('/admin/questions', [QuestionController::class, 'store']);
    Route::delete('/admin/questions/{question}', [QuestionController::class, 'destroy']);
    Route::get('/admin/question-types', [QuestionTypeController::class, 'list']);
    Route::get('/admin/matches', [SearchController::class, 'matchesList']);
    Route::get('/admin/nomatches', [SearchController::class, 'noMatchesList']);
    Route::put('/admin/nomatches/done/{noMatches}', [SearchController::class, 'updateNoMatches']);
    Route::post('/admin/answers', [AnswerController::class, 'store']);
    Route::delete('/admin/answers/{answer}', [AnswerController::class, 'destroy']);
    Route::post('/admin/categories/{category}/images', [CategoryController::class, 'storeImage']);
    Route::post('/admin/categories/{category}/icon', [CategoryController::class, 'storeIcon']);
    Route::post('/admin/categories', [CategoryController::class, 'store']);
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update']);
    Route::get('/admin/categories', [CategoryController::class, 'adminList']);
    Route::get('/admin/user/check-email/{email}', [UserController::class, 'AdminEmailExist']);

    Route::get('/admin/dashboard/users', [DashboardController::class, 'registeredUsers']);
    Route::get('/admin/dashboard/companies', [DashboardController::class, 'registeredCompanies']);
    Route::get('/admin/dashboard/matches', [DashboardController::class, 'totalMatches']);
    Route::get('/admin/dashboard/nomatches', [DashboardController::class, 'totalNomatches']);
    Route::get('/admin/dashboard/services', [DashboardController::class, 'topServices']);
    Route::get('/admin/dashboard/stats', [DashboardController::class, 'getStats']);

    Route::post('/admin/companies/welcome-notification', [CompanyController::class, 'companyWelcomeNotification']);
    Route::get('/admin/companies/services/{slug}/{company_id}', [ServiceController::class, 'adminGetService']);
    Route::get('/admin/companies/payment-methods/{company}', [PaymentController::class, 'adminGetPaymentsMethodsCompany']);
    //Companies Service Config
    Route::post('/admin/companies/services/zipcodes', [ServiceController::class, 'adminZipcodesByRegion']);
    Route::post('/admin/companies/services/states', [ServiceController::class, 'adminStoreState']);
    Route::post('/admin/companies/services/remove', [ServiceController::class, 'adminRemoveService']);
    Route::post('/admin/companies/services', [ServiceController::class, 'adminStore']);
    Route::post('/admin/companies/services/zipcodes/add', [ServiceController::class, 'adminServiceAddZipcode']);
    Route::post('/admin/companies/services/states/remove', [CompanyController::class, 'adminRemoveState']);
    Route::post('/admin/companies/services/zipcodes/delete', [ServiceController::class, 'adminServiceRemoveZipcode']);
    Route::post('/admin/companies/services/zipcodes/county/add', [ServiceController::class, 'adminServiceAddZipcodesByCounty']);
    Route::post('/admin/companies/services/zipcodes/county/remove', [ServiceController::class, 'adminServiceRemoveZipcodesByCounty']);
    Route::post('/admin/companies/services/zipcodes/all', [ServiceController::class, 'adminServiceSelectAllState']);
    Route::post('/admin/companies/services/zipcodes/remove', [ServiceController::class, 'adminRemoveAllState']);
    Route::post('/admin/companies/services/states/copy', [CompanyController::class, 'adminServicesCopyStates']);
    Route::post('/admin/companies/{company}/images', [CompanyController::class, 'adminStoreImages']);
    Route::delete('/admin/companies/images/{image}', [CompanyController::class, 'adminDeleteImage']);
    Route::get('/admin/payments/all-charges', [PaymentController::class, 'getAllCharges']);
    Route::get('/admin/payments/balance', [PaymentController::class, 'getBalance']);
    Route::post('/admin/payments/recharge', [PaymentController::class, 'recharge']);
    Route::post('/admin/send-lead', [SearchController::class, 'sendLead']);
    Route::get('/admin/transactions', [TransactionController::class, 'list']);


    //AI system
    Route::post('/admin/ai/questions', [AIController::class, 'questions']);
});



Route::post('/send-verification-code', [VerificationCodeController::class, 'sendSmsVerification'])->middleware(['web', 'auth:sanctum']);
Route::post('/verification-code', [VerificationCodeController::class, 'verifyCode'])->middleware(['web', 'auth:sanctum']);

Route::get('/mautic', [MauticController::class, 'token']);
Route::get('/mautic/callback', [MauticController::class, 'callback']);
Route::post('/push/send', [PushNotificationController::class, 'sendPushNotificationEndpoint'])->middleware(['web', 'auth:sanctum']);
