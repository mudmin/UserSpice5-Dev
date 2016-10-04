<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com
*/

ini_set("allow_url_fopen", 1);
require_once 'init.php';
require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/header.php';

/*
Secures the page...required for page permission management
*/
if (!securePage($_SERVER['PHP_SELF'])){die();}

/*
Initialize variables for the page
*/
$errors=[];
$successes=[];

/*
If $_POST data exists, then check CSRF token, and kill page if not correct...no need to process rest of page or form data
*/
if (Input::exists()) {
	if(!Token::check(Input::get('csrf'))){
		die('Token doesn\'t match!');
	}
}

$reCaptchaValid=FALSE;
$createSuccess=FALSE;

if(Input::exists()){
	
	$username = Input::get('username');
	$fname = Input::get('fname');
	$lname = Input::get('lname');
	$email = Input::get('email');
	$company = Input::get('company');
	$agreement_checkbox = Input::get('agreement_checkbox');	
	
	
	/*
	If recaptcha is enabled, then process recaptcha and response
	*/
	if($site_settings->recaptcha == 1){
		$remoteIp=$_SERVER["REMOTE_ADDR"];
		$gRecaptchaResponse=Input::get('g-recaptcha-response');
		$response = null;
		
		require_once 'includes/recaptcha.config.php';

		// check secret key
		$reCaptcha = new ReCaptcha($site_settings->recaptcha_private);

		// if submitted check response
		if ($gRecaptchaResponse) {
			$response = $reCaptcha->verifyResponse($remoteIp,$gRecaptchaResponse);
		}
		if ($response != null && $response->success) {
			$reCaptchaValid=TRUE;
		}else{
			$reCaptchaValid=FALSE;
			$errors[]='Please check the reCaptcha';
		}
	}else{
		/*
		If reCaptcha is disabled, then set true so that the following sequence will run
		*/
		$reCaptchaValid=TRUE;
	}
	
	/*
	If agreement checkbox not checked, then add the error
	*/	
	if ($agreement_checkbox=='on'){
		$agreement_checkbox=TRUE;
	}else{
		$agreement_checkbox=FALSE;
	}

	if (!$agreement_checkbox){
		$errors[]='Please read and accept terms and conditions';
	}

	if($reCaptchaValid || $site_settings->recaptcha == 0){ //if recaptcha valid or recaptcha disabled

		/*
		Perform input validation prior to creating account
		*/
		
		$validation = new Validate();
		$validation->check($_POST,array(
		  'username' => array('display' => 'Username','required' => true,'min' => 5,'max' => 35,'unique' => 'users',),
		  'fname' => array('display' => 'First Name','required' => true,'min' => 2,'max' => 35,),
		  'lname' => array('display' => 'Last Name','required' => true,'min' => 2,'max' => 35,),
		  'email' => array('display' => 'Email','required' => true,'valid_email' => true,'unique' => 'users',),
		  'company' => array('display' => 'Company Name','required' => false,'min' => 0,'max' => 75,),
		  'password' => array('display' => 'Password','required' => true,'min' => 6,'max' => 25,),
		  'confirm' => array('display' => 'Confirm Password','required' => true,'matches' => 'password',),
		));		

		if ($validation->passed() && $agreement_checkbox) {
			/*
			If validation passes then create user
			*/
			$vericode = rand(100000,999999);
			
			$user = new User();
			$join_date = date("Y-m-d H:i:s");
			/*
			URL includes the <a> and </a> tags, including the url string plus the link text
			*/
			$url='<a href="'.$site_settings->site_url.'/users/verify.php?email='.urlencode($email).'&vericode='.$vericode.'">Verify your email</a>';
			$options = array(
			  'fname' => $fname,
			  'url' => $url,
			  'sitename' => $site_settings->site_name,
			);

			if($site_settings->email_act == 1) {
				/*
				If email activation is enabled, then set email account as not verified and prepare and send email
				*/
				$email_verified=0;
				$subject = 'Welcome to '.$site_settings->site_name.'!';
				$body = email_body($site_settings->email_verify_template,$options);
				email($email,$subject,$body);
			}else{
				/*
				Email activation is not enabled, so just flag the account as email verified
				*/
				$email_verified=1;
			}
			try {
				// echo "Trying to create user";
				$user->create(array(
					'username' => Input::get('username'),
					'fname' => Input::get('fname'),
					'lname' => Input::get('lname'),
					'email' => Input::get('email'),
					'password' =>
					password_hash(Input::get('password'), PASSWORD_BCRYPT, array('cost' => 12)),
					'permissions' => 1,
					'account_owner' => 1,
					'stripe_cust_id' => '',
					'join_date' => $join_date,
					'company' => Input::get('company'),
					'email_verified' => $email_verified,
					'active' => 1,
					'vericode' => $vericode,
				));
			} catch (Exception $e) {
				die($e->getMessage());
			}
			$createSuccess=TRUE;			
			
		}else{
			/*
			Append validation errors to error array
			*/
			foreach ($validation->errors() as $error) {
				$errors[]=$error;
			}
		}
	}
} //Input exists

?>

<div class="row">
<div class="col-xs-12">

<?php
if (!$createSuccess){
?>
	<h2>Sign Up</h2>
	<?=display_errors($errors);?>
	<form class="form-signup" action="join.php" method="post">
		
	<div class="form-group">
		<label for="username">Choose a Username</label>
		<input  class="form-control" type="text" name="username" id="username" placeholder="Username" value="<?=$username;?>" required autofocus>
		<p class="help-block">No Spaces or Special Characters - Min 5 characters</p>
	</div>
	<div class="form-group">
		<label for="fname">First Name</label>
		<input type="text" class="form-control" id="fname" name="fname" placeholder="First Name" value="<?=$fname;?>" required>
	</div>
	<div class="form-group">
		<label for="lname">Last Name</label>
		<input type="text" class="form-control" id="lname" name="lname" placeholder="Last Name" value="<?=$lname;?>" required>
	</div>
	<div class="form-group">
		<label for="email">Email Address</label>
		<input  class="form-control" type="text" name="email" id="email" placeholder="Email Address" value="<?=$email;?>" required >
	</div>
	<div class="form-group">
		<label for="company">Company Name</label>
		<input type="text" class="form-control" id="company" name="company" placeholder="Company Name" value="<?=$company;?>">
	</div>
	<div class="form-group">
		<label for="password">Choose a Password</label>
		<input  class="form-control" type="password" name="password" id="password" placeholder="Password" required aria-describedby="passwordhelp">
		<span class="help-block" id="passwordhelp">Must be at least 6 characters</span>
	</div>
	<div class="form-group">
		<label for="confirm">Confirm Password</label>
		<input  type="password" id="confirm" name="confirm" class="form-control" placeholder="Confirm Password" required >
	</div>
	<div class="form-group">
		<label for="confirm">Registration User Terms and Conditions</label>
		<textarea id="agreement" name="agreement" rows="5" class="form-control" disabled ><?php require ABS_US_ROOT.US_URL_ROOT.'usersc/includes/user_agreement.php'; ?></textarea>
	</div>
	<div class="form-group">
		<label for="confirm">Check box to agree to terms</label>
		<input type="checkbox" id="agreement_checkbox" name="agreement_checkbox" class="form-control">
	</div>
	<?php if($settings->recaptcha == 1){ ?>
	<div class="form-group">
		<div class="g-recaptcha" data-sitekey="<?=$site_settings->recaptcha_public; ?>"></div>
	</div>
	<?php } ?>
	<input type="hidden" value="<?=Token::generate();?>" name="csrf">
	<div class="text-center"><button class="submit btn btn-primary" type="submit" id="next_button"><span class="fa fa-plus-square"></span> Sign Up</button></div>
	</form>
<?php
}else{
	if($site_settings->email_act==0){
?>
		<div class="jumbotron text-center">
		<h2>Welcome To <?=$site_settings->site_name?>!</h2>
		<p>Thanks for registering!</p>
		<a href="login.php" class="btn btn-primary">Login</a>
		</div>
<?php
	}else{
?>	
		<div class="jumbotron text-center">
		<h2>Welcome To <?=$site_settings->site_name?>!</h2>
		<p>Thanks for registering! Please check your email to verify your account.</p>
		</div>
<?php
	}	
}
?>

</div>
</div>

<!-- footers -->
<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls ?>

<?php 	if($site_settings->recaptcha == 1){ ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php } ?>
<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/html_footer.php'; // currently just the closing /body and /html ?>