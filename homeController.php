<?php

namespace App\Http\Controllers\frontend;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redirect;
use App\User;
use Auth;
use Session;
use Flash;
use Hash;
use DB;
use Mail;
use Form;
use HTML;
use App\Category;
use App\hellman;
use App\Subcategory;
use App\Products;
use Intervention\Image\Facades\Image;
use Closure;
use App\Otherproducts;
use App\Query;
use App\News;


class homeController extends Controller
{

    public function homePage(){
        /*
         * pass available category sub-category and products from this function
         * get all the available categories from the database
         */
        $popular1 = DB::table('otherproducts')
                    ->where('type', 'popular')
                    ->first();

        $latest1 = DB::table('otherproducts')
            ->where('type', 'latest')
            ->first();

        $popular = DB::table('otherproducts')
            ->where('type', 'popular')
            ->orderBy('id', 'DESC')
            ->get();

        $latest = DB::table('otherproducts')
            ->where('type', 'latest')
            ->orderBy('id', 'DESC')
            ->get();

        $allfeatures = DB::table('featured')
            ->join('products', 'products.id', '=', 'featured.product_id')
            ->get();

        $allnews = DB::table('news')->get();

        $categories = Category::with('subCategory')->get();
        $products   = DB::table('products')
            ->join('users', 'products.user_id', '=', 'users.id')
            ->orderBy('products.created_at', 'desc')
            ->paginate(9);

       return view('pages.main')
            ->with(array(
                'categories'   => $categories,
                'products'     => $products,
                'feature'      => $allfeatures,
                'popular1'      => $popular1,
                'latest1'       => $latest1,
                'popular'       => $popular,
                'latest'        => $latest,
                'news'          => $allnews
            ));
    }


    /*
     * displaying registration view to the user
     */
    public function registerView(){

        $categories = Category::with('subCategory')->get();
        $make=hellman::with('allproducts')->get();

        return view('pages.login.register')->with('categories', $categories);
    }
    public function registerViewamba(){
        return view('pages.login.registeram');
    }

    public function postRegister(Request $request){
        /*
         * getting input from the user
         */
        $registrationValidation = validator::make($request->all(), [
           'first_name'         =>  'required',
            'last_name'         =>  'required',
            'email'             =>  'required|email|unique:users',
            'password'          =>  'required|Between:8,20',
            'confirm_password'  =>  'required|same:password',
            'mobile'            =>  'required|numeric|min:10',
            'address'           =>  'required',
            'location'          =>  'required',

        ]);

        /*
         * check whether validation is successful or not
         */
        if ($registrationValidation->fails()){
            return redirect()->back()
                ->withErrors($registrationValidation->messages())
                ->withInput(Input::except('password', 'confirm_password'));
        }
        else{

           /*
            * Insert data into database with the confirmed status zero
            * Send verification link to user for the registration
            * And activate the account when user verifies the email.
            */
            $confirmation_code = str_random(30);

            /*
             * checking whether the user has chosen to display phone no. or not
             */

            if (Input::get('display_phone') != 1){
                $displayPhoneStatus = 0;
            }
            else{
                $displayPhoneStatus = 1;
            }

            // create the data for new user
            $user = New User;
            $user->first_name           =   Input::get('first_name');
            $user->last_name            =   Input::get('last_name');
            $user->email                =   Input::get('email');
            $user->password             =   Hash::make(Input::get('password'));
            $user->mobile               =   Input::get('mobile');
            $user->address              =   Input::get('address');
            $user->type                 =   'user';
            $user->location             =   Input::get('location');
            $user->display_phone        =   $displayPhoneStatus;
            $user->confirmation_code    =   $confirmation_code;

            $user->save();

            /*
           *Now send email to the user to verify the email address
           */

            Mail::send('emails.verify', ['code' => $confirmation_code], function($message){
                $message->to(Input::get('email'), Input::get('first_name') . ' '. Input::get('last_name'))->subject('Secondhand.com.np. Email Verification');
            });

            // redirect ----------------------------------------
            // redirect our user back to the form so they can do it all over again
            return Redirect::back()
                ->with('message', 'SignUp Successful. Please check your mail to verify your email');
        }
    }

    /*
     * Checking the confirmation code of the user
     * This will verify the email address of the user
     */
    public function confirmRegistration($code){
        if( ! $code)
        {
            throw new InvalidConfirmationCodeException;
        }

        $user = User::whereConfirmationCode($code)->first();

        if (!$user)
        {
            throw new InvalidConfirmationCodeException;
        }

        $user->confirmed = 1;
        $user->confirmation_code = null;
        $user->save();

        return Redirect::to('startjourney')
            ->withMessage('Success !! Email verified successfully. Please login');
    }


    /*
     * Function to validate the user login informations
     */
    public function postLogin(Request $request){

        $loginValidation = validator::make($request->all(),[
            'loginemail'     =>   'required|email|exists:users,email',
            'loginpassword'  =>   'required|min:8'
        ]);

        if ($loginValidation->fails()){
            return redirect::back()
                ->withErrors($loginValidation->errors())
                ->withInput(Input::except('password'));
        }

        else{
            /*
            *getting the email and password that user has typed in form
            */
            $loginData = array(
                'email' => Input::get('loginemail'),
                'password' => Input::get('loginpassword'),
                'confirmed' => 1,
                'type'  => 'user'
            );

            if (Auth::validate($loginData)) {
                if (Auth::attempt($loginData)) {
                    return Redirect::intended('home');
                }
            }
            else {
                // if any error send back with message.
                Session::flash('error', 'Invalid Email/Password Combination');
                return Redirect::to('startjourney');
            }

        }
    }

    /*
     * **********Display the profile of the user*************
     */

    public function userProfile(){
        $categories = Category::with('subCategory')->get();
        return view('pages.profile.userprofile')->with(array(
            'categories' => $categories
        ));
    }


    /*
     *  ***********Display the register ad view to the user
     */
    public function registerAd(){
        $listCategories = Category::with('subCategory')->get();
            return view('pages.profile.postad')
                ->with(array(
                    'categories'     => $listCategories,
                ));
    }

    /*
     * Handles all necessary logics for uploading the new advertisement
     */
    public function uploadAd($subcategory_slug){
        /*
         * first identify the current subcategory that user want the upload the ad
         * Then render the special form if necessary to upload that ad
         */
        $categories = Category::with('subCategory')->get();
        $slug = $subcategory_slug;
        return view('pages.ads.main')
            ->with(array(
                'categoryslug' => $slug,
                'categories' => $categories
            ));
    }

    /*
     * this controller will display the detail of the subcategory to the users
     */
    public function findProduct(Request $request){
        /*
         * getting search  term
         */
        $allnews = DB::table('news')->get();

        $searchTerm = Input::get('product');

        /*
         * fetch all related product from database
         */
        $fetchProducts = DB::table('products')
                        ->join('users', 'users.id', '=', 'products.user_id')
                        ->where('products.title', 'like', '%'.$searchTerm.'%')
                        ->paginate(8);

       /*
        * now fetch all the subcategory belonging to that category
        */
        $categories = Category::with('subCategory')->get();

        /*
         *featured products
         */
        $allfeatures = DB::table('featured')
            ->join('products', 'products.id', '=', 'featured.product_id')
            ->get();

        return view('pages.searchdetails')->with(array(
            'categories'     => $categories,
            'result'        => $fetchProducts,
            'feature'   => $allfeatures,
            'news'      => $allnews
        ));


    }

    /*
     * Handling user checkout function
     */
    public function shoppingCart($slug){

        $allnews = DB::table('news')->get();

        $popular1 = DB::table('otherproducts')
            ->where('type', 'popular')
            ->first();

        $latest1 = DB::table('otherproducts')
            ->where('type', 'latest')
            ->first();

        $popular = DB::table('otherproducts')
            ->where('type', 'popular')
            ->orderBy('id', 'DESC')
            ->get();

        $latest = DB::table('otherproducts')
            ->where('type', 'latest')
            ->orderBy('id', 'DESC')
            ->get();

        $categories = Category::with('subCategory')->get();

        $product = DB::table('products')
                    ->where('product_slug', $slug)
                    ->first();

        $allfeatures = DB::table('featured')
            ->join('products', 'products.id', '=', 'featured.product_id')
            ->get();

        return view('pages.shopping')->with(array(
            'product'    => $product,
            'categories' => $categories,
            'feature'   => $allfeatures,
            'popular1'      => $popular1,
            'latest1'       => $latest1,
            'popular'       => $popular,
            'latest'        => $latest,
            'news'      => $allnews
        ));
    }


    /*
     * downloadable product shopping cart
     */
    public function downloadableShoppingCart($slug){

        $allnews = DB::table('news')->get();
        $popular1 = DB::table('otherproducts')
            ->where('type', 'popular')
            ->first();

        $latest1 = DB::table('otherproducts')
            ->where('type', 'latest')
            ->first();

        $popular = DB::table('otherproducts')
            ->where('type', 'popular')
            ->orderBy('id', 'DESC')
            ->get();

        $latest = DB::table('otherproducts')
            ->where('type', 'latest')
            ->orderBy('id', 'DESC')
            ->get();

        $categories = Category::with('subCategory')->get();

        $product = DB::table('downloadable_products')
            ->where('product_slug', $slug)
            ->first();

        $allfeatures = DB::table('featured')
            ->join('products', 'products.id', '=', 'featured.product_id')
            ->get();

        $email = Auth::user()->email;

        return view('pages.downloadableshopping')->with(array(
            'product'    => $product,
            'categories' => $categories,
            'feature'   => $allfeatures,
            'email'     => $email,
            'popular1'      => $popular1,
            'latest1'       => $latest1,
            'popular'       => $popular,
            'latest'        => $latest,
            'news'      => $allnews
        ));
    }


    public function shoppingSuccess($product){


        /*
         * Remove the product from the product list as well as from the featured section or mark it as sold
         */
        DB::table('products')
            ->where('id', '=', $product)
            ->update(['status' =>  'sold']);

        return redirect::to('/')->with('message', 'Item has been purchased successfully'.$product);
    }


    public function shoppingFailed(){
        return redirect::to('/')->with('error', 'There was problem processing your request. Please try later');
    }


    /*
     * displaying downloadable product in the frontend
     */
    public function listDownloadable(){
        $allnews = DB::table('news')->get();

        $popular1 = DB::table('otherproducts')
            ->where('type', 'popular')
            ->first();

        $latest1 = DB::table('otherproducts')
            ->where('type', 'latest')
            ->first();

        $popular = DB::table('otherproducts')
            ->where('type', 'popular')
            ->orderBy('id', 'DESC')
            ->get();

        $latest = DB::table('otherproducts')
            ->where('type', 'latest')
            ->orderBy('id', 'DESC')
            ->get();

        $categories = Category::with('subCategory')->get();

        $allfeatures = DB::table('featured')
            ->join('products', 'products.id', '=', 'featured.product_id')
            ->get();

        $products = DB::table('downloadable_products')->paginate(8);

        return view('pages.downloadable')
            ->with(array(
                'categories'    =>  $categories,
                'feature'   => $allfeatures,
                'product'    => $products,
                'news'      => $allnews,
                'popular1'      => $popular1,
                'latest1'       => $latest1,
                'popular'       => $popular,
                'latest'        => $latest
            ));
    }


    public function downloadableSuccess($product, $email){

        $mailofUser = $email;

        $getLink = DB::table('downloadable_products')
                        ->where('id', $product)
                        ->first();

        $linktosend = $getLink->link;

        /*
        *Now send email to the user to verify the email address
        */



        Mail::send('emails.downloadable', ['link' => $linktosend], function($message) use($mailofUser){

            $message->to($mailofUser)->subject('Secondhand.com.np. Software product purchase');
        });


        return redirect::to('home')->with('message', 'Download link for the software has been sent to your email.');
    }

    /*
     * posting customer query
     */
    public function postQuery(Request $request){

        $name    = Input::get('name');
        $email   = Input::get('email');
        $subject = Input::get('subject');
        $message = Input::get('message');

        $post = new Query;
        $post->name      = $name;
        $post->email     = $email;
        $post->subject   = $subject;
        $post->message   = $message;
        $post->sent_date = date('Y-m-d');

        $post->save();

        return redirect::to('/')->with('message', 'Query is successfully submitted');
    }


    public function newsDetails($slug){

        $getNews = DB::table('news')
                    ->where('slug', $slug)
                    ->first();

        $categories = Category::with('subCategory')->get();

        $allfeatures = DB::table('featured')
            ->join('products', 'products.id', '=', 'featured.product_id')
            ->get();

        $popular1 = DB::table('otherproducts')
            ->where('type', 'popular')
            ->first();

        $latest1 = DB::table('otherproducts')
            ->where('type', 'latest')
            ->first();

        $popular = DB::table('otherproducts')
            ->where('type', 'popular')
            ->orderBy('id', 'DESC')
            ->get();

        $latest = DB::table('otherproducts')
            ->where('type', 'latest')
            ->orderBy('id', 'DESC')
            ->get();

        return view('pages.news')
            ->with(array(
                'categories'    =>  $categories,
                'feature'       => $allfeatures,
                'popular1'      => $popular1,
                'latest1'       => $latest1,
                'popular'       => $popular,
                'latest'        => $latest,
                'details'       => $getNews

            ));
    }

    /*
     * user logout
     */
    public function userlogout(){
        Auth::logout(); // logout user
        Session::flush();
        return Redirect::to('/')
            ->with('message', 'You have been successfully logged out'); //redirect back to login
    }
}
