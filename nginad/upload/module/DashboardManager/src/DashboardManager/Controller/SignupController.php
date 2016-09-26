<?php
/**
 * NGINAD Project
 *
 * @link http://www.nginad.com
 * @copyright Copyright (c) 2013-2016 NginAd Foundation. All Rights Reserved
 * @license GPLv3
 */
namespace DashboardManager\Controller;

use DashboardManager\ParentControllers\PublisherAbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Mail\Message;
use Zend\Mime;

/**
 * @author Kelvin Mok
 * This is the Signup Controller class that controls the management
 * of signup functions.
 */
class SignupController extends PublisherAbstractActionController {

    /**
     * Display the signup index page.
     * 
     * @return \Zend\View\Model\ViewModel
     */
	public function indexAction()
	{	    
	    $auth = $this->getServiceLocator()->get('AuthService');
		if ($auth->hasIdentity()):
			$initialized = $this->initialize();
			if ($initialized === true) return $this->redirect()->toRoute($this->dashboard_home);
    	endif;
	    
    	$http_host = @$_SERVER['HTTP_HOST'];
    	if ($http_host != null):
	    	$PrivateExchangeVanityDomainFactory = \_factory\PrivateExchangeVanityDomain::get_instance();
	    	
	    	$params = array();
	    	$params["VanityDomain"] = strtolower($http_host);
	    	$PrivateExchangeVanityDomain = $PrivateExchangeVanityDomainFactory->get_row($params);
	    	
	    	if ($PrivateExchangeVanityDomain != null):
	    		return \util\AuthHelper::login($this);
	    	endif;
    	endif;
    	
	    $view = new ViewModel(array(
	    		'dashboard_view' => 'signup',
	    		'vertical_map' => \util\DeliveryFilterOptions::$vertical_map
	    ));
	    
	    return $view;
	}
	
	public function checkduplicateAction()
	{
		$email = $this->getRequest()->getQuery('email');
		$user_login = $this->getRequest()->getQuery('login');
		// Check if an entry exists with the same name. A NULL means there is no duplicate.
	
		$authUsersFactory	 		= \_factory\authUsers::get_instance();
	
		$result1 = $authUsersFactory->get_row(array("user_email" => $email)) === null;
		$result2 = $authUsersFactory->get_row(array("user_login" => $user_login)) === null;

		$result = 'success';
		$messages = array();
		
		if (!$result1):
			$result = 'error';
			$messages[] = 'That email address is already in use. Please select another';
		endif;
		
		if (!$result2):
			$result = 'error';
			$messages[] = 'That username is already in use. Please select another';
		endif;
		
		if ($result == 'success'):
			$data = array(
					'result' => 'success'
			);
		else:
			$data = array(
					'result' => 'error',
					'message' => implode('<br/>', $messages)
			);
		endif;
		
		$this->setJsonHeader();
		return $this->getResponse()->setContent(json_encode($data));
	}
	
	public function customerAction()
	{	    
		$auth = $this->getServiceLocator()->get('AuthService');
		$config = $this->getServiceLocator()->get('Config');
		if ($auth->hasIdentity()):
			$initialized = $this->initialize();
			if ($initialized === true) $this->redirect()->toRoute($this->dashboard_home);
    	endif;
    	
    	$error_msg = null;
		$success_msg = null;
    	$request = $this->getRequest();
		if ($request->isPost()):
			$Name	     = $request->getPost('customer_name');
			$Email		 = $request->getPost('email');
			$Website	 = $request->getPost('website');
			$Company	 = $request->getPost('company');
			$PartnerType = intval($request->getPost('partner_type'));
			$Password	 = $request->getPost('password');
			$user_login	 = $request->getPost('user_login');
			
			$Password = str_replace(array("'",";"), array("",""), $Password);
			
			if (preg_match('/[^-_. 0-9A-Za-z]/', $Name)
				|| !filter_var($Email, FILTER_VALIDATE_EMAIL)
				|| empty($Website) || preg_match('/[^-_. 0-9A-Za-z]/', $Website)
				|| empty($Company) || preg_match('/[^-_. 0-9A-Za-z]/', $Company)
				|| empty($PartnerType)
				|| empty($Password)
				|| !ctype_alnum($user_login)):
			
				die("Invalid Registration Data");
			endif;
			
			$DemandCustomerInfo = new \model\DemandCustomerInfo();
			$DemandCustomerInfoFactory = \_factory\DemandCustomerInfo::get_instance();
			
			$DemandCustomerInfo->Name		    							= 	$Name;
			$DemandCustomerInfo->Email										=	$Email;
			$DemandCustomerInfo->Website									=	$Website;
			$DemandCustomerInfo->Company									=	$Company;
			$DemandCustomerInfo->PartnerType								=	$PartnerType;
			$DemandCustomerInfo->DateCreated								=	date("Y-m-d H:i:s");
			$DemandCustomerInfo->ApprovedForPlatformConnectionInventory		=	0;
			$DemandCustomerInfo->ApprovedForSspRtbInventory					=	0;
			$DemandCustomerInfo->CreditApplicationWasSent					=	0;
			$DemandCustomerInfo->DateCreditApplicationWasSent				=	'0000-00-00 00:00:00';
				
			
			$authUsers = new \model\authUsers();
			$authUsersFactory = \_factory\authUsers::get_instance();

			// Check if an entry exists with the same name. A NULL means there is no duplicate.
		    if ($DemandCustomerInfoFactory->get_row(array("Email" => $DemandCustomerInfo->Email)) === null && $authUsersFactory->get_row(array("user_login" => $user_login)) === null):

				$lastInsertID = $DemandCustomerInfoFactory->saveCustomerInfo($DemandCustomerInfo);
				
				$authUsers->DemandCustomerInfoID  	= $lastInsertID;
				$authUsers->user_login		      	= $user_login;
				$authUsers->user_email		      	= $Email;
				$authUsers->user_password	      	= \util\Password::md5_split_salt($Password);
				$authUsers->user_role		      	= 2; //role as domain admin
				$authUsers->user_enabled	      	= 0; 
				$authUsers->user_verified         	= 0; 
				$authUsers->user_agreement_accepted = 0;
				$authUsers->parent_id				= 0;
				$authUsers->create_date	   	      	= date("Y-m-d H:i:s");
				
				$authUsersFactory->saveUser($authUsers);
				$success_msg = 1;
				
				if ($config['mail']['subscribe']['signups'] === true):
					
					$partner_type = isset(\util\DeliveryFilterOptions::$partner_type[$PartnerType]) ? \util\DeliveryFilterOptions::$partner_type[$PartnerType] : "N/A";
	
					$message = '<b>New NginAd Demand Customer Registered.</b><br /><br />';
					$message = $message.'<table border="0" width="10%">';
					$message = $message.'<tr><td><b>Login: </b></td><td>'.$user_login.'</td></tr>';
					$message = $message.'<tr><td><b>Name: </b></td><td>'.$Name.'</td></tr>';
					$message = $message.'<tr><td><b>Email: </b></td><td>'.$Email.'</td></tr>';
					$message = $message.'<tr><td><b>Website: </b></td><td>'.$Website.'</td></tr>';
					$message = $message.'<tr><td><b>Company: </b></td><td>'.$Company.'</td></tr>';
					$message = $message.'<tr><td><b>Partner Type: </b></td><td>'.$partner_type.'</td></tr>';
					$message = $message.'</table>';
					
					$subject = "New NginAd Demand Customer Registered: " . $user_login;
					
					$transport = $this->getServiceLocator()->get('mail.transport');
					
					$text = new Mime\Part($message);
					$text->type = Mime\Mime::TYPE_HTML;
					$text->charset = 'utf-8';
					
					$mimeMessage = new Mime\Message();
					$mimeMessage->setParts(array($text));
					$zf_message = new Message();
		
					$zf_message->addTo($config['mail']['admin-email']['email'], $config['mail']['admin-email']['name'])
						->addFrom($config['mail']['reply-to']['email'], $config['mail']['reply-to']['name'])
						->setSubject($subject)
						->setBody($mimeMessage);
					$transport->send($zf_message);
					
				endif;

			else:
				$error_msg = "ERROR: A duplicate Account may exist. Please try another.";
			endif;
		endif;
    	
	    
	    $view = new ViewModel(array(
	    		'dashboard_view' => 'signup',
	    		'error_msg' => $error_msg,
	    		'success_msg' => $success_msg,
	    		'partner_type' => \util\DeliveryFilterOptions::$partner_type
	    ));
	    
	    return $view;
	}
	
	public function pxnewuserAction() {
	
		$request = $this->getRequest();
		if (!$request->isPost()):
			return $this->redirect()->toRoute('pxnewpublisher');
		endif;

		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
	
		$config = $this->getServiceLocator()->get('Config');
	
		$Name	     = $request->getPost('Name');
		$Email		 = $request->getPost('Email');
		$Domain		 = $request->getPost('Domain');
		$IABCategory = $request->getPost('IABCategory');
		$Password	 = $request->getPost('Password');
		$user_login	 = $request->getPost('user_login');
	
		$Password = str_replace(array("'",";"), array("",""), $Password);
	
		if (preg_match('/[^-_. 0-9A-Za-z]/', $Name)
			|| !filter_var($Email, FILTER_VALIDATE_EMAIL)
			|| empty($Domain)|| preg_match('/[^-_. 0-9A-Za-z]/', $Domain)
			|| empty($IABCategory) || preg_match('/[^-_. 0-9A-Za-z]/', $IABCategory)
			|| empty($Password)
			|| !ctype_alnum($user_login)):
			
			die("Invalid Registration Data");
		endif;
	
		$PublisherInfo = new \model\PublisherInfo();
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
	
		$PublisherInfo->Name		    = 	$Name;
		$PublisherInfo->Email			=	$Email;
		$PublisherInfo->Domain			=	$Domain;
		$PublisherInfo->IABCategory		=	$IABCategory;
		$PublisherInfo->DateCreated		=	date("Y-m-d H:i:s");
	
		$error_msg = null;
		$success_msg = null;
	
	
		$authUsers = new \model\authUsers();
		$authUsersFactory = \_factory\authUsers::get_instance();
	
	
		// Check if an entry exists with the same name. A NULL means there is no duplicate.
		if ($PublisherInfoFactory->get_row(array("Email" => $PublisherInfo->Email)) === null && $authUsersFactory->get_row(array("user_login" => $user_login)) === null):
		 
			$lastInsertID = $PublisherInfoFactory->savePublisherInfo($PublisherInfo);
				
			$authUsers->PublisherInfoID  			= $lastInsertID;
			$authUsers->parent_id					= $this->auth->getUserID();
			$authUsers->user_login		 			= $user_login;
			$authUsers->user_email		 			= $Email;
			$authUsers->user_password	 			= \util\Password::md5_split_salt($Password);
			$authUsers->user_role		 			= 3; //role as member
			$authUsers->user_enabled     			= 1;
			$authUsers->user_verified    			= 1;
			$authUsers->user_agreement_accepted	   	= 1;
			$authUsers->create_date	   	 			= date("Y-m-d H:i:s");
				
			$authUsersFactory->saveUser($authUsers);
			$success_msg = 1;
				
			if ($config['mail']['subscribe']['signups'] === true):
				
				$iab_cat = isset(\util\DeliveryFilterOptions::$vertical_map[$IABCategory]) ? \util\DeliveryFilterOptions::$vertical_map[$IABCategory] : "N/A";
			
				$message = '<b>New NginAd Publisher Registered.</b><br /><br />';
				$message = $message.'<table border="0" width="10%">';
				$message = $message.'<tr><td><b>Login: </b></td><td>'.$user_login.'</td></tr>';
				$message = $message.'<tr><td><b>Name: </b></td><td>'.$Name.'</td></tr>';
				$message = $message.'<tr><td><b>Email: </b></td><td>'.$Email.'</td></tr>';
				$message = $message.'<tr><td><b>Domain: </b></td><td>'.$Domain.'</td></tr>';
				$message = $message.'<tr><td><b>IABCategory: </b></td><td>'.$iab_cat.'</td></tr>';
				$message = $message.'</table>';
			
				$subject = "New Private Exchange Publisher Registered: " . $user_login;
			
				$transport = $this->getServiceLocator()->get('mail.transport');
			
				$text = new Mime\Part($message);
				$text->type = Mime\Mime::TYPE_HTML;
				$text->charset = 'utf-8';
			
				$mimeMessage = new Mime\Message();
				$mimeMessage->setParts(array($text));
				$zf_message = new Message();
			
				$zf_message->addTo($config['mail']['admin-email']['email'], $config['mail']['admin-email']['name'])
				->addFrom($config['mail']['reply-to']['email'], $config['mail']['reply-to']['name'])
				->setSubject($subject)
				->setBody($mimeMessage);
				$transport->send($zf_message);
			endif;
		else:
			$error_msg = "ERROR: A duplicate Account may exist. Please try another.";
		endif;
	
		return $this->redirect()->toRoute('pxpublisherlist',
				array(
						'error_msg' => $error_msg
				)
				);
	
	}
	
	public function newuserAction() {
		
		$request = $this->getRequest();
		if (!$request->isPost()):
			 return $this->redirect()->toRoute('signup');
		endif;
		
		$config = $this->getServiceLocator()->get('Config');
		
		$Name	     = $request->getPost('Name');
		$Email		 = $request->getPost('Email');
		$Domain		 = $request->getPost('Domain');
		$IABCategory = $request->getPost('IABCategory');
		$Password	 = $request->getPost('Password');
		$user_login	 = $request->getPost('user_login');
		
		$Password = str_replace(array("'",";"), array("",""), $Password);
		
		if (preg_match('/[^-_. 0-9A-Za-z]/', $Name)
			|| !filter_var($Email, FILTER_VALIDATE_EMAIL)
			|| empty($Domain)|| preg_match('/[^-_. 0-9A-Za-z]/', $Domain)
			|| empty($IABCategory) || preg_match('/[^-_. 0-9A-Za-z]/', $IABCategory)
			|| empty($Password)
			|| !ctype_alnum($user_login)):
			
			die("Invalid Registration Data");
		endif;
		
		$PublisherInfo = new \model\PublisherInfo();
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
		
		$PublisherInfo->Name		    = 	$Name;
		$PublisherInfo->Email			=	$Email;
		$PublisherInfo->Domain			=	$Domain;
		$PublisherInfo->IABCategory		=	$IABCategory;
		$PublisherInfo->DateCreated		=	date("Y-m-d H:i:s");
		
		$error_msg = null;
		$success_msg = null;
		
		
		$authUsers = new \model\authUsers();
		$authUsersFactory = \_factory\authUsers::get_instance();

		
		// Check if an entry exists with the same name. A NULL means there is no duplicate.
	    if ($PublisherInfoFactory->get_row(array("Email" => $PublisherInfo->Email)) === null && $authUsersFactory->get_row(array("user_login" => $user_login)) === null):
	            
			$lastInsertID = $PublisherInfoFactory->savePublisherInfo($PublisherInfo);
			
			$authUsers->PublisherInfoID  			= $lastInsertID;
			$authUsers->parent_id					= 0;
			$authUsers->user_login		 			= $user_login;
			$authUsers->user_email		 			= $Email;
			$authUsers->user_password	 			= \util\Password::md5_split_salt($Password);
			$authUsers->user_role		 			= 3; //role as member
			$authUsers->user_enabled     			= 0; 
			$authUsers->user_verified    			= 0;
			$authUsers->user_agreement_accepted	   	= 0;
			$authUsers->create_date	   	 			= date("Y-m-d H:i:s");
			
			$authUsersFactory->saveUser($authUsers);
			$success_msg = 1;
			
			if ($config['mail']['subscribe']['signups'] === true):
			
				$iab_cat = isset(\util\DeliveryFilterOptions::$vertical_map[$IABCategory]) ? \util\DeliveryFilterOptions::$vertical_map[$IABCategory] : "N/A";
				
				$message = '<b>New NginAd Publisher Registered.</b><br /><br />';
				$message = $message.'<table border="0" width="10%">';
				$message = $message.'<tr><td><b>Login: </b></td><td>'.$user_login.'</td></tr>';
				$message = $message.'<tr><td><b>Name: </b></td><td>'.$Name.'</td></tr>';
				$message = $message.'<tr><td><b>Email: </b></td><td>'.$Email.'</td></tr>';
				$message = $message.'<tr><td><b>Domain: </b></td><td>'.$Domain.'</td></tr>';
				$message = $message.'<tr><td><b>IABCategory: </b></td><td>'.$iab_cat.'</td></tr>';
				$message = $message.'</table>';
				
				$subject = "New NginAd Publisher Registered: " . $user_login;
				
				$transport = $this->getServiceLocator()->get('mail.transport');
				
				$text = new Mime\Part($message);
				$text->type = Mime\Mime::TYPE_HTML;
				$text->charset = 'utf-8';
				
				$mimeMessage = new Mime\Message();
				$mimeMessage->setParts(array($text));
				$zf_message = new Message();
				
				$zf_message->addTo($config['mail']['admin-email']['email'], $config['mail']['admin-email']['name'])
				->addFrom($config['mail']['reply-to']['email'], $config['mail']['reply-to']['name'])
				->setSubject($subject)
				->setBody($mimeMessage);
				$transport->send($zf_message);
			endif;
		else:
			$error_msg = "ERROR: A duplicate Account may exist. Please try another.";
		endif;
		
		$view = new ViewModel(array(
	    		'error_msg' => $error_msg,
	    		'success_msg' => $success_msg,
	    		'vertical_map' => \util\DeliveryFilterOptions::$vertical_map,
	    		'dashboard_view' => 'signup'
	    ));
	    
	    return $view->setTemplate('dashboard-manager/signup/index.phtml');
		
	}
	
	// user account view and update
	public function pxnewpublisherAction() {
	
	
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		$success_msg = null;

		$userData = array();
		$view = new ViewModel(array(
				'dashboard_view' => 'account',
				'user_identity' => $this->identity(),
				'success_msg' => $success_msg,
				'user_tab' => 'profile',
				'user_data' => $userData,
				'vertical_map' => \util\DeliveryFilterOptions::$vertical_map,
				'user_id_list' => $this->user_id_list,
				'user_identity' => $this->identity(),
				'true_user_name' => $this->auth->getUserName(),
				'header_title' => 'Add New Private Exchange Publisher',
				'is_super_admin' => $this->is_super_admin,
				'effective_id' => $this->auth->getEffectiveIdentityID(),
				'impersonate_id' => $this->ImpersonateID
		));
		 
		return $view->setTemplate('dashboard-manager/signup/px-add-publisher.phtml');
	}
	
	// user account view and update
	public function accountAction() {

		 $auth = $this->getServiceLocator()->get('AuthService');
		 if (!$auth->hasIdentity()):
     	 	return $this->redirect()->toRoute('login');
    	 endif;
    	 
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		$success_msg = null;
		
		$authUsers = new \model\authUsers();
		$authUsersFactory = \_factory\authUsers::get_instance();
		
		$PublisherInfo = new \model\PublisherInfo();
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
				
		$userData = $authUsersFactory->get_row(array("user_id" => $this->auth->getUserID()));
		$userRole = $this->auth->getRoles();
		$userRole = $userRole[0];

		$request = $this->getRequest();
	    if ($request->isPost()):
	    	$user_id	 = $request->getPost('user_id');
	    	$name	     = $request->getPost('name');
			$description = $request->getPost('description');
			
			if($userRole == 'member'):
				$PublisherInfo = $PublisherInfoFactory->get_row_object(array("PublisherInfoID" => $userData->PublisherInfoID));
				$PublisherInfo->PublisherInfoID = $userData->PublisherInfoID;
				$PublisherInfo->Name		    = $name;
				$PublisherInfo->DateUpdated		= date("Y-m-d H:i:s");
				$PublisherInfoFactory->savePublisherInfo($PublisherInfo);
			endif;
			
			$authUsers = $authUsersFactory->get_row_object(array("user_id" => $this->auth->getUserID()));
			
			$authUsers->user_id 	     = $user_id;
			$authUsers->user_fullname 	 = $name;
			$authUsers->user_description = $description;
			$authUsers->update_date	   	 = date("Y-m-d H:i:s");
			$authUsersFactory->saveUser($authUsers);
			
			$success_msg = 1;
	    endif;
		
		$userData = $authUsersFactory->get_row(array("user_id" => $this->auth->getUserID()));
		$userRole = $this->auth->getRoles();
		$userRole = $userRole[0];
		
		// check if user-role is memeber (publisher)
		if($userRole == 'member'):
			$publisherData = $PublisherInfoFactory->get_row(array("PublisherInfoID" => $userData->PublisherInfoID));
			$userData['user_email'] = $publisherData['Email'];
			$userData['user_fullname'] = $publisherData['Name'];
		endif;
	
		$view = new ViewModel(array(
	    		'dashboard_view' => 'account',
	    		'user_identity' => $this->identity(),
	    		'success_msg' => $success_msg,
	    		'user_tab' => 'profile',
	    		'user_data' => $userData,
	            'user_id_list' => $this->user_id_list,
	            'user_identity' => $this->identity(),
		    	'true_user_name' => $this->auth->getUserName(),
				'header_title' => 'Account Settings',
				'is_super_admin' => $this->is_super_admin,
				'is_domain_admin' => $this->is_domain_admin,
				'effective_id' => $this->auth->getEffectiveIdentityID(),
				'impersonate_id' => $this->ImpersonateID
	    ));
	    
	  return $view->setTemplate('dashboard-manager/auth/account.phtml');
	}
	
	//password update
	public function changepasswordAction() {

		 $auth = $this->getServiceLocator()->get('AuthService');
		 if (!$auth->hasIdentity()):
     	 	return $this->redirect()->toRoute('login');
    	 endif;
    	 
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		$success_msg = null;
		$success_msg1 = null;
		$error_msg = null;
		
		$authUsers = new \model\authUsers();
		$authUsersFactory = \_factory\authUsers::get_instance();
		
		$PublisherInfo = new \model\PublisherInfo();
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
				
		$userData = $authUsersFactory->get_row(array("user_id" => $this->auth->getUserID()));
		$userRole = $this->auth->getRoles();
		$userRole = $userRole[0];

		$request = $this->getRequest();
	    if ($request->isPost()):
	    	$user_id	 = $request->getPost('user_id');
	    	$old_password = trim($request->getPost('old_password'));
	    	$password =    trim($request->getPost('password'));

			$authUsers = $authUsersFactory->get_row_object(array("user_id" => $this->auth->getUserID()));
			$authUsers->user_id 	     = $user_id;
			$authUsers->user_password 	 = \util\Password::md5_split_salt($password);
			$authUsers->update_date	   	 = date("Y-m-d H:i:s");
			
			$userData = $authUsersFactory->get_row(array("user_id" => $authUsers->user_id));
			if($userData->user_password == \util\Password::md5_split_salt($old_password)):
				$authUsersFactory->saveUser($authUsers);
			    $success_msg1 = 1;
			else: 
				$error_msg = "Old password is incorrect.";
			endif;
	     endif;
		
		// check if user-role is memeber (publisher)
		if($userRole == 'member'):
			$publisherData = $PublisherInfoFactory->get_row(array("PublisherInfoID" => $userData->PublisherInfoID));
			$userData['user_email'] = $publisherData['Email'];
			$userData['user_fullname'] = $publisherData['Name'];
		endif;
	
		$view = new ViewModel(array(
	    		'dashboard_view' => 'account',
	    		'user_identity' => $this->identity(),
	    		'success_msg' => $success_msg,
	    		'success_msg1' => $success_msg1,
	    		'user_tab' => 'password',
	    		'error_msg' => $error_msg,
	    		'user_data' => $userData,
	            'user_id_list' => $this->user_id_list,
	            'user_identity' => $this->identity(),
		    	'true_user_name' => $this->auth->getUserName(),
				'header_title' => 'Account Settings',
				'is_super_admin' => $this->is_super_admin,
				'is_domain_admin' => $this->is_domain_admin,
				'effective_id' => $this->auth->getEffectiveIdentityID(),
				'impersonate_id' => $this->ImpersonateID
	    ));
	    
	  return $view->setTemplate('dashboard-manager/auth/changepassword.phtml');
	}

	public function publishersdomainAction() {
	
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		$authUsersFactory = \_factory\authUsers::get_instance();
	
		$userData = $authUsersFactory->get_row(array("user_id" => $this->auth->getUserID()));

		if (!$this->is_domain_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
	
		/*
		 * Only get publishers created by the domain admin 
		 * for the private exchange
		 */
		
		$childAccountData = $authUsersFactory->get(array("parent_id" => $this->auth->getUserID()));
		$PublisherInfo = new \model\PublisherInfo();
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
		
		$userDetail = array();
		
		foreach ($childAccountData as $childAccount):
			
			$userDetail[] = $PublisherInfoFactory->get_row(array("PublisherInfoID" => $childAccount->PublisherInfoID));
			
		endforeach;
		
		$view = new ViewModel(array(
				'dashboard_view' => 'account',
				'user_detail' => $userDetail,
				'authUsersFactory' => $authUsersFactory,
				'user_type' => 'publisher',
				'user_id' => $this->auth->getUserID(),
				'user_id_list' => $this->user_id_list,
				'user_identity' => $this->identity(),
				'true_user_name' => $this->auth->getUserName(),
				'header_title' => 'Private Exchange Publishers List',
				'is_domain_admin' => $this->is_domain_admin,
				'effective_id' => $this->auth->getEffectiveIdentityID(),
				'impersonate_id' => $this->ImpersonateID
		));
		 
		return $view->setTemplate('dashboard-manager/auth/publishersdomain.phtml');
	
	}
	
	public function publishersAction() {
		
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		$authUsersFactory = \_factory\authUsers::get_instance();

		if (!$this->is_super_admin) :
     		return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
		
		$orders = 'DateCreated DESC'; 	    
		$userDetail = $PublisherInfoFactory->get(null, $orders);

		$view = new ViewModel(array(
	    	'dashboard_view' => 'account',
	    	'user_detail' => $userDetail,
	    	'authUsersFactory' => $authUsersFactory,
	    	'user_type' => 'publisher',
	    	'user_id' => $this->auth->getUserID(),
	       	'user_id_list' => $this->user_id_list,
	      	'user_identity' => $this->identity(),
		  	'true_user_name' => $this->auth->getUserName(),
			'header_title' => 'Publishers List',
			'is_super_admin' => $this->is_super_admin,
			'effective_id' => $this->auth->getEffectiveIdentityID(),
			'impersonate_id' => $this->ImpersonateID
	    ));
	    
	  return $view->setTemplate('dashboard-manager/auth/publishers.phtml');

	}
	
	public function managecreditAction() {
	
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
	
		if (!$this->is_super_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$id = $this->getEvent()->getRouteMatch()->getParam('param1');
		if ($id == null):
			die("Invalid Customer ID");
		endif;

		$DemandCustomerInfoFactory = \_factory\DemandCustomerInfo::get_instance();
		
		$params["DemandCustomerInfoID"] = $id;	    
		$DemandCustomerInfo = $DemandCustomerInfoFactory->get_row($params);
		
		if ($DemandCustomerInfo == null):
			die("Invalid Customer ID");
		endif;

		$email_sent = $this->getRequest()->getQuery('emailsent');
		$email_sent = ($email_sent == "true") ? true : false;
		
		$authUsersFactory = \_factory\authUsers::get_instance();
		
		$view = new ViewModel(array(
	    	'dashboard_view' => 'account',
			'email_sent' => $email_sent,
	    	'user_data' => $DemandCustomerInfo,
			'authUsersFactory' => $authUsersFactory,
	    	'user_type' => 'customer',
	    	'user_id' => $this->auth->getUserID(),
	       	'user_id_list' => $this->user_id_list,
	      	'user_identity' => $this->identity(),
		  	'true_user_name' => $this->auth->getUserName(),
			'header_title' => 'Manage Customer Credit',
			'is_super_admin' => $this->is_super_admin,
			'effective_id' => $this->auth->getEffectiveIdentityID(),
			'impersonate_id' => $this->ImpersonateID
	    ));
		
		return $view->setTemplate('dashboard-manager/auth/managecredit.phtml');
	}
	
	public function sendcreditapplicationAction() {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$id = $this->getEvent()->getRouteMatch()->getParam('param1');
		if ($id == null):
			die("Invalid Customer ID");
		endif;
		
		$DemandCustomerInfoFactory = \_factory\DemandCustomerInfo::get_instance();
		
		$params["DemandCustomerInfoID"] = $id;
		$DemandCustomerInfo = $DemandCustomerInfoFactory->get_row($params);
		
		if ($DemandCustomerInfo == null):
			die("Invalid Customer ID");
		endif;
		
		$transport = $this->getServiceLocator()->get('mail.transport');
		
		\util\CreditHelper::sendCreditApplication($transport, $this->config_handle, $DemandCustomerInfo->DemandCustomerInfoID);
		
		return $this->redirect()->toUrl('/users/managecredit/' . $DemandCustomerInfo->DemandCustomerInfoID . "?emailsent=true");
	}
	
	public function approvessprtbinventoryAction() {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$id = $this->getEvent()->getRouteMatch()->getParam('param1');
		if ($id == null):
			die("Invalid Customer ID");
		endif;
		
		$DemandCustomerInfoFactory = \_factory\DemandCustomerInfo::get_instance();
		
		$params["DemandCustomerInfoID"] = $id;
		$DemandCustomerInfo = $DemandCustomerInfoFactory->get_row($params);
		
		if ($DemandCustomerInfo == null):
			die("Invalid Customer ID");
		endif;
		
		$approve_flag = $this->getRequest()->getQuery('approve');
		$approve_flag = ($approve_flag == "true") ? true : false;

		$DemandCustomerInfoFactory->approvedForSspRtbInventory($DemandCustomerInfo->DemandCustomerInfoID, $approve_flag);
		
		return $this->redirect()->toUrl('/users/managecredit/' . $DemandCustomerInfo->DemandCustomerInfoID);
	}
	
	public function approveplatformconnectioninventoryAction() {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$id = $this->getEvent()->getRouteMatch()->getParam('param1');
		if ($id == null):
			die("Invalid Customer ID");
		endif;
		
		$DemandCustomerInfoFactory = \_factory\DemandCustomerInfo::get_instance();
		
		$params["DemandCustomerInfoID"] = $id;
		$DemandCustomerInfo = $DemandCustomerInfoFactory->get_row($params);
		
		if ($DemandCustomerInfo == null):
			die("Invalid Customer ID");
		endif;
		
		$approve_flag = $this->getRequest()->getQuery('approve');
		$approve_flag = ($approve_flag == "true") ? true : false;
		
		$DemandCustomerInfoFactory->approvedForPlatfromConnectionInventory($DemandCustomerInfo->DemandCustomerInfoID, $approve_flag);
		
		return $this->redirect()->toUrl('/users/managecredit/' . $DemandCustomerInfo->DemandCustomerInfoID);
	}
	
	public function customersAction() {
		
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;

		$authUsersFactory = \_factory\authUsers::get_instance();

		if (!$this->is_super_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$DemandCustomerInfoFactory = \_factory\DemandCustomerInfo::get_instance();
		
		$orders = 'DateCreated DESC'; 	    
		$userDetail = $DemandCustomerInfoFactory->get(null, $orders);

		$view = new ViewModel(array(
	    	'dashboard_view' => 'account',
	    	'user_detail' => $userDetail,
	    	'authUsersFactory' => $authUsersFactory,
	    	'user_type' => 'customer',
	    	'user_id' => $this->auth->getUserID(),
	       	'user_id_list' => $this->user_id_list,
	      	'user_identity' => $this->identity(),
		  	'true_user_name' => $this->auth->getUserName(),
			'header_title' => 'Demand Customers List',
			'is_super_admin' => $this->is_super_admin,
			'effective_id' => $this->auth->getEffectiveIdentityID(),
			'impersonate_id' => $this->ImpersonateID
	    ));
	    
	  return $view->setTemplate('dashboard-manager/auth/customers.phtml');

	}
	
	
	public function rejectuserAction() {
		
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin && !$this->is_domain_admin):
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$request = $this->getRequest();
		if ($request->isPost()):
			$user_id = $request->getPost('user_id');
	    	$description = $request->getPost('description');
	    	$user_type = $request->getPost('user_type');
	    	
	    	if ($this->is_domain_admin):
		    	if (!\util\AuthHelper::domain_user_authorized_publisher($this->auth->getUserID(), $user_id)):
		    		die("Not Authorized");
		    	endif;
	    	endif;
	    	
	    	if($user_type == 'publisher'):
	    		return $this->rejectpublisherAction($user_id, $description, $user_type);
	    	endif;
	    	if($user_type == 'customer'):
	    		return $this->rejectcustomerAction($user_id, $description, $user_type);
	    	endif;
		endif;
	}
	
	public function acceptuserAction() {
		
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin && !$this->is_domain_admin):
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$request = $this->getRequest();
		if ($request->isPost()):
			$user_id = $request->getPost('user_id');
	    	$user_type = $request->getPost('user_type');
	    	
	    	if ($this->is_domain_admin):
	    		if (!\util\AuthHelper::domain_user_authorized_publisher($this->auth->getUserID(), $user_id)):
	    			die("Not Authorized");
	    		endif;
	    	endif;
	    	
	    	if($user_type == 'publisher'):
	    		return $this->acceptpublisherAction($user_id, $user_type);
	    	endif;
	    	if($user_type == 'customer'):
	    		return $this->acceptcustomerAction($user_id, $user_type);
	    	endif;
		endif;
	}
	
	
	public function rejectpublisherAction($publisher_id, $description, $user_type) {

		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin && !$this->is_domain_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		if ($this->is_domain_admin):
			if (!\util\AuthHelper::domain_user_authorized_publisher($this->auth->getUserID(), $publisher_id)):
				die("Not Authorized");
			endif;
		endif;
		
		$msg = null;
		$success = false;
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
		
			$publisher_obj = $PublisherInfoFactory->get_row_object(array('PublisherInfoID'=>$publisher_id));
	        $bol = $this->userApprovalToggle(0, $publisher_id, $user_type);

	        if($bol == true):
	          
	          $message = '<b>Publisher Rejected.<b><br />';
	          $message = $message.$description;

			  $subject = "Publisher rejected.";
			  
			  $transport = $this->getServiceLocator()->get('mail.transport');
			  
			  $text = new Mime\Part($message);
			  $text->type = Mime\Mime::TYPE_HTML;
			  $text->charset = 'utf-8';
			  
			  $mimeMessage = new Mime\Message();
			  $mimeMessage->setParts(array($text));
			  $zf_message = new Message();
			  $zf_message->addTo($publisher_obj->Email)
				  ->addFrom($this->config_handle['mail']['reply-to']['email'], $this->config_handle['mail']['reply-to']['name'])
				  ->setSubject($subject)
				  ->setBody($mimeMessage);
			  $transport->send($zf_message);
			  
	          $success = true;
	          $msg = 'Publisher rejected. Email sent successfully.';
	       endif;
        
        //endif;
		
		$data = array(
	        'success' => $success,
	        'data' => array('msg' => $msg)
   		 );

		$this->setJsonHeader();
        return $this->getResponse()->setContent(json_encode($data));
	}
	
	
	public function acceptpublisherAction($publisher_id, $user_type) {

		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin && !$this->is_domain_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		if ($this->is_domain_admin):
			if (!\util\AuthHelper::domain_user_authorized_publisher($this->auth->getUserID(), $publisher_id)):
				die("Not Authorized");
			endif;
		endif;
		
		$msg = null;
		$success = false;
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
		
	        $bol = $this->userApprovalToggle(1, $publisher_id, $user_type);
	        
	        if($bol == true):
	          $publisher_obj = $PublisherInfoFactory->get_row_object(array('PublisherInfoID'=>$publisher_id));
	          
		        $site_url 		= $this->config_handle['delivery']['site_url'];
		        $exchange_name 	= $this->config_handle['delivery']['exchange_name'];
		        
				$message = 'Your ' . $exchange_name . ' Publisher account was approved.<br /><br />Please login <a href="' . $site_url . '/auth/login">here</a> with your email and password';
	
				$subject = "Your " . $exchange_name . " Publisher account was approved";
					
				$transport = $this->getServiceLocator()->get('mail.transport');
					
				$text = new Mime\Part($message);
				$text->type = Mime\Mime::TYPE_HTML;
				$text->charset = 'utf-8';
					
				$mimeMessage = new Mime\Message();
				$mimeMessage->setParts(array($text));
				$zf_message = new Message();
				$zf_message->addTo($publisher_obj->Email)
					->addFrom($this->config_handle['mail']['reply-to']['email'], $this->config_handle['mail']['reply-to']['name'])
					->setSubject($subject)
					->setBody($mimeMessage);
				$transport->send($zf_message);
				
		        $success = true;
		        $msg = 'Publisher approved. Email sent successfully.';
	       endif;
        
        //endif;
		
		$data = array(
	        'success' => $success,
	        'data' => array('msg' => $msg)
   		 );

		$this->setJsonHeader();
        return $this->getResponse()->setContent(json_encode($data));
	}
	
	
	public function rejectcustomerAction($customer_id, $description, $user_type) {

		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$msg = null;
		$success = false;
		$DemandCustomerFactory = \_factory\DemandCustomerInfo::get_instance();
		
			$customer_obj = $DemandCustomerFactory->get_row_object(array('DemandCustomerInfoID'=>$customer_id));
	        $bol = $this->userApprovalToggle(0, $customer_id, $user_type);
	        if($bol == true):
	          
	          $message = '<b>Customer Rejected.<b><br />';
	          $message = $message.$description;

			  $subject = "Customer rejected.";
			  
			  $transport = $this->getServiceLocator()->get('mail.transport');
			  
			  $text = new Mime\Part($message);
			  $text->type = Mime\Mime::TYPE_HTML;
			  $text->charset = 'utf-8';
			  
			  $mimeMessage = new Mime\Message();
			  $mimeMessage->setParts(array($text));
			  $zf_message = new Message();
			  $zf_message->addTo($customer_obj->Email)
				  ->addFrom($this->config_handle['mail']['reply-to']['email'], $this->config_handle['mail']['reply-to']['name'])
				  ->setSubject($subject)
				  ->setBody($mimeMessage);
			  $transport->send($zf_message);
			  
	          $success = true;
	          $msg = 'Customer rejected. Email sent successfully.';
	       endif;
        
        //endif;
		
		$data = array(
	        'success' => $success,
	        'data' => array('msg' => $msg)
   		 );

		$this->setJsonHeader();
        return $this->getResponse()->setContent(json_encode($data));
	}
	
	
	public function acceptcustomerAction($customer_id, $user_type) {

		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin) :
			return $this->redirect()->toRoute($this->dashboard_home);
		endif;
		
		$msg = null;
		$success = false;
		$DemandCustomerFactory = \_factory\DemandCustomerInfo::get_instance();
		
	        $bol = $this->userApprovalToggle(1, $customer_id, $user_type);
	        
	        if($bol == true):
	           $customer_obj = $DemandCustomerFactory->get_row_object(array('DemandCustomerInfoID'=>$customer_id));

		        $site_url 		= $this->config_handle['delivery']['site_url'];
		        $exchange_name 	= $this->config_handle['delivery']['exchange_name'];
		        
				$message = 'Your ' . $exchange_name . ' Demand Customer account was approved.<br /><br />Please login <a href="' . $site_url . '/auth/login">here</a> with your email and password';
	
				$subject = "Your " . $exchange_name . " Demand Customer account was approved";
					
				$transport = $this->getServiceLocator()->get('mail.transport');
					
				$text = new Mime\Part($message);
				$text->type = Mime\Mime::TYPE_HTML;
				$text->charset = 'utf-8';
				
				$mimeMessage = new Mime\Message();
				$mimeMessage->setParts(array($text));
				$zf_message = new Message();
				$zf_message->addTo($customer_obj->Email)
					->addFrom($this->config_handle['mail']['reply-to']['email'], $this->config_handle['mail']['reply-to']['name'])
					->setSubject($subject)
					->setBody($mimeMessage);
				$transport->send($zf_message);
				
		        $success = true;
		        $msg = 'Customer approved. Email sent successfully.';
	       endif;
        
        //endif;
		
		$data = array(
	        'success' => $success,
	        'data' => array('msg' => $msg)
   		 );

		$this->setJsonHeader();
        return $this->getResponse()->setContent(json_encode($data));
	}
	
	
	
	
	private function userApprovalToggle($flag, $user_id, $user_type)
	{
	    	         
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
	
	    if (($this->is_super_admin || $this->is_domain_admin)
	    	&& $user_id > 0 && ($flag === 1 || $flag === 0)):
	    
	    	if ($this->is_domain_admin):
		    	if (!\util\AuthHelper::domain_user_authorized_publisher($this->auth->getUserID(), $user_id)):
		    		die("Not Authorized");
		    	endif;
	    	endif;
	    	
	    	$authUsers = new \model\authUsers();
			$authUsersFactory = \_factory\authUsers::get_instance();
			
			if($user_type=='publisher'):
				$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
				$authUsers = $authUsersFactory->get_row_object(array("PublisherInfoID" => $user_id));
	        endif;
	        if($user_type=='customer'):
				$DemandCustomerFactory = \_factory\DemandCustomerInfo::get_instance();
				$authUsers = $authUsersFactory->get_row_object(array("DemandCustomerInfoID" => $user_id));
	        endif;

	   		$authUsers->user_enabled 						= $flag;
    		$authUsers->user_verified 						= $flag;
    		$authUsers->user_agreement_accepted				= 0;

    		if($flag === 1):
    			$authUsersFactory->saveUser($authUsers);
    			return true;
    		endif;
    		if($flag === 0):
    		  if($user_type=='publisher'):
    			//$PublisherInfoFactory->deletePublisherInfo($user_id);
    			;
    		  endif;
    		  if($user_type=='customer'):
    			//$DemandCustomerFactory->deleteCustomerInfo($user_id);
    			;
    		  endif;		
    		    $authUsersFactory->deactivate_user($authUsers->user_id);
    			return true;
    		endif;
    	endif;	
	    return false;
	}
	
	
	public function websitesAction() {
		
		$auth = $this->getServiceLocator()->get('AuthService');
		if (!$auth->hasIdentity()):
     		return $this->redirect()->toRoute('login');
    	endif;
		
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		$error_msg = null;
		$success_msg = null;
		$PublisherWebsite = new \model\PublisherWebsite();
		$PublisherWebsiteFactory = \_factory\PublisherWebsite::get_instance();
			
		
		$request = $this->getRequest();
	    if ($request->isPost()):
	        
	    	$website = $request->getPost('website');
	    	$category = $request->getPost('category');
	    	$PublisherWebsite->WebDomain = $website;
	    	$PublisherWebsite->IABCategory = $category;
	    	$PublisherWebsite->DomainOwnerID = $this->auth->getPublisherInfoID();
	    	$PublisherWebsite->DateCreated = date("Y-m-d H:i:s");
	    	$PublisherWebsite->Description = "";
	    	
	    	$PublisherWebsite->VisibilityTypeID = \util\AuthHelper::isPrivateExchangePublisher($this->auth->getPublisherInfoID()) === true ? 2 : 1;
	    	
	    	$auto_approve_websites = $this->config_handle['settings']['publisher']['auto_approve_websites'];
	    	
	    	$PublisherWebsite->AutoApprove = ($auto_approve_websites == true) ? 1 : 0;

	    	// Disapprove the changes if not admin.
	    	if ($this->is_super_admin || $auto_approve_websites == true):
	    		$PublisherWebsite->ApprovalFlag = 1;
	    	else:
	    		$PublisherWebsite->ApprovalFlag = 0;
	    	endif;
	    	
	    	$PublisherWebsite->IABCategory = $category;
	    	
	    	$params = array();
	    	$params["WebDomain"] = $PublisherWebsite->WebDomain;
	    	$params["DomainOwnerID"] = $this->auth->getPublisherInfoID();
	    	
	    	if ($PublisherWebsiteFactory->get_row($params) === null):
	    	  	$PublisherWebsiteFactory->save_domain($PublisherWebsite);
	    	
	    		if ($auto_approve_websites != true || $this->config_handle['mail']['subscribe']['websites'] === true):
	    		
	    			if ($auto_approve_websites != true):
			    	  	$message = "New NginAd Website for Approval.<br /><b>".$website."</b><br /><br />Username: " . $this->true_user_name;
						$subject = "New NginAd Website for Approval: " . $website;
					else:
						$message = "New NginAd Website.<br /><b>".$website."</b><br /><br />Username: " . $this->true_user_name;
						$subject = "New NginAd Website: " . $website;
					endif;
					
					$transport = $this->getServiceLocator()->get('mail.transport');
					
					$text = new Mime\Part($message);
					$text->type = Mime\Mime::TYPE_HTML;
					$text->charset = 'utf-8';
					
					$mimeMessage = new Mime\Message();
					$mimeMessage->setParts(array($text));
					$zf_message = new Message();
					$zf_message->addTo($this->config_handle['mail']['admin-email']['email'], $this->config_handle['mail']['admin-email']['name'])
						->addFrom($this->config_handle['mail']['reply-to']['email'], $this->config_handle['mail']['reply-to']['name'])
						->setSubject($subject)
						->setBody($mimeMessage);
					$transport->send($zf_message);
				endif;
				
		  		$success_msg = 1;
		  	else:
		  		$error_msg = '"' . $website . '" duplicate entry.';
		  	endif;	
		endif;
		
    
	    $pending_list = $PublisherWebsiteFactory->get(array('DomainOwnerID' => $this->auth->getPublisherInfoID(), 'ApprovalFlag' => 0));
	    $approved_list = $PublisherWebsiteFactory->get(array('DomainOwnerID' => $this->auth->getPublisherInfoID(), 'ApprovalFlag' => 1));
	    $denied_list = $PublisherWebsiteFactory->get(array('DomainOwnerID' => $this->auth->getPublisherInfoID(), 'ApprovalFlag' => 2));
		
		$view = new ViewModel(array(
	    	'dashboard_view' => 'account',
	    	'pending_list' => $pending_list,
	    	'approved_list' => $approved_list,
	    	'denied_list' => $denied_list,
	    	'success_msg' => $success_msg,
	    	'error_msg' => $error_msg,
	    	'vertical_map' => \util\DeliveryFilterOptions::$vertical_map,
	    	'user_id' => $this->auth->getUserID(),
	       	'user_id_list' => $this->user_id_list,
	      	'user_identity' => $this->identity(),
		  	'true_user_name' => $this->auth->getUserName(),
			'header_title' => 'Account Settings',
			'is_super_admin' => $this->is_super_admin,
			'is_domain_admin' => $this->is_domain_admin,
			'effective_id' => $this->auth->getEffectiveIdentityID(),
			'impersonate_id' => $this->ImpersonateID
	    ));
	    
	    return $view;
	}
	
	public function pxwebsitesAction() {
	
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->auth->hasIdentity()):
			return $this->redirect()->toRoute('login');
		elseif (!$this->is_domain_admin):
			return $this->redirect()->toRoute('publisher');
		endif;
		
		$error_msg = null;
		$success_msg = null;
		$PublisherWebsiteFactory = \_factory\PublisherWebsite::get_instance();

		$authUsersFactory 			= \_factory\authUsers::get_instance();
		$params = array();
		$params["parent_id"] 		= $this->auth->getUserID();
		$authUserChildlist		 	= $authUsersFactory->get($params);
		
		$PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
		
		$pending_list = array();
		$approved_list = array();
		$denied_list = array();
		
		foreach ($authUserChildlist as $authUserChild):
			$orders = 'DateCreated DESC';
			$params = array();
			$params["DomainOwnerID"] = $authUserChild->PublisherInfoID;
			
			$params["ApprovalFlag"] = 0;
			$websites_list = $PublisherWebsiteFactory->get($params, $orders);
			$pending_list = array_merge($pending_list, $websites_list);
				
			$params["ApprovalFlag"] = 1;
			$websites_list = $PublisherWebsiteFactory->get($params, $orders);
			$approved_list = array_merge($approved_list, $websites_list);
			
			$params["ApprovalFlag"] = 2;
			$websites_list = $PublisherWebsiteFactory->get($params, $orders);
			$denied_list = array_merge($denied_list, $denied_list);
			
		endforeach;

		$view = new ViewModel(array(
				'dashboard_view' => 'account',
				'pending_list' => $pending_list,
				'approved_list' => $approved_list,
				'denied_list' => $denied_list,
				'success_msg' => $success_msg,
				'error_msg' => $error_msg,
				'vertical_map' => \util\DeliveryFilterOptions::$vertical_map,
				'user_id' => $this->auth->getUserID(),
				'user_id_list' => $this->user_id_list,
				'user_identity' => $this->identity(),
				'true_user_name' => $this->auth->getUserName(),
				'header_title' => 'Account Settings',
				'is_super_admin' => $this->is_super_admin,
				'is_domain_admin' => $this->is_domain_admin,
				'effective_id' => $this->auth->getEffectiveIdentityID(),
				'impersonate_id' => $this->ImpersonateID
		));
		 
		return $view->setTemplate('dashboard-manager/signup/websites.phtml');
	}
	
	public function deletewebsiteAction() {
		
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		$auth = $this->getServiceLocator()->get('AuthService');
		if (!$auth->hasIdentity()):
     		return $this->redirect()->toRoute('login');
    	endif;
		
		$success = false;
		$msg = null;
		$PublisherWebsiteFactory = \_factory\PublisherWebsite::get_instance();
		$PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
		
		$publisher_website_data = null;
		
		$request = $this->getRequest();
	    if ($request->isPost()):
	        
	    	$website_id = intval($request->getPost('website_id'));

	    	if ($this->auth->isDomainAdmin($this->config_handle)):
	    		$authorized = \util\AuthHelper::domain_user_authorized_publisher_website($this->auth->getUserID(), $website_id);
	    	
	    		if ($authorized === true):
			    	$params = array();
			    	$params["PublisherWebsiteID"] 		= $website_id;
			    	$publisher_website_data = $PublisherWebsiteFactory->get_row($params);
		    	endif;
	    	else:
		    	$params = array();
		    	$params["PublisherWebsiteID"] 		= $website_id;
		    	$params["DomainOwnerID"] 			= $this->auth->getPublisherInfoID();
		    	$publisher_website_data = $PublisherWebsiteFactory->get_row($params);
	    	endif;
	    	
	    	if ($publisher_website_data == null):
	    	
	    		$msg = 'Authorization Error.';
	    	
	    	else:
		    	
		    	$success = true;
		    	$PublisherWebsiteFactory->delete_domain($website_id);
		    	 
		    	$params = array();
		    	$params['PublisherWebsiteID'] = $website_id;
		    	$PublisherAdZoneList = $PublisherAdZoneFactory->get($params);
		    	 
		    	foreach ($PublisherAdZoneList as $PublisherAdZone):
		    	
		    		$PublisherAdZoneFactory->delete_zone(intval($PublisherAdZone->PublisherAdZoneID));
		    	
		    	endforeach;
		    	 
		    	$msg = '"' . $publisher_website_data->WebDomain . '" removed successfully.';
		    	
	    	endif;
	    	
	    endif;	
		
		$data = array(
	        'success' => $success,
	        'data' => array('msg' => $msg)
   		 );

		$this->setJsonHeader();
        return $this->getResponse()->setContent(json_encode($data));
	} 
	
	public function getAuthService()
	{
		return $this->getServiceLocator()->get('AuthService');
	}
}

?>