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
use transformation;
use Zend\Mail\Message;
use Zend\Mime;

/**
 * @author Kelvin Mok
 * This is the Ad spaces Controller class that controls the management
 * of ad space management functions.
 */
class ZoneController extends PublisherAbstractActionController {
    
    protected $ad_template_data; 
    
    /**
     * Query for initial domain data necessary to obtain the associated object information, and
     * to verify that the Domain associated with the ad space is valid.
     * 
     * @param integer $DomainID An integer of the domain ID associated with the ad spaces.
     * @param integer $DomainOwnerID The domain owner ID associated with the domain ID.
     * @throws \InvalidArgumentException will be thrown when an integer is not supplied to the function parameters.
     * @throws Exception will be thrown when there is a database error while the module is in Debug mode.
     * @return NULL|\DashboardManager\model\PublisherWebsite Returns a NULL when no matching domains are found. Otherwise, the domain object is returned.
     */
    protected function get_domain_data($DomainID, $DomainOwnerID)
    {
        if (!is_int($DomainID) || !is_int($DomainOwnerID)):
        
            throw new \InvalidArgumentException(
                "ZoneController class, get_domain_data function expects an integer for \$DomainID and \$DomainOwnerID;" .
                " However, type " . gettype($DomainID) . " and type " . gettype($DomainOwnerID) . " was provided instead."
            );
        endif;

        //Initialize and define variables.
        $PublisherWebsiteFactory = \_factory\PublisherWebsite::get_instance();
        $PublisherWebsiteListObj = new \model\PublisherWebsite;
        $parameters = array(); // Set the parameters to empty first.
        
        //Populate parameters.
        $parameters['DomainOwnerID'] = $DomainOwnerID;
        $parameters['PublisherWebsiteID'] = $DomainID;

        // Pull website information.
        try {
        $PublisherWebsiteListObj = $PublisherWebsiteFactory->get_row_object($parameters);
        }
        catch(\Exception $e)
        {
            // If there is a DB error, return an empty object as if no rows were found!
            if ($this->debug):
            
                throw $e;
            endif;
            return null;
        }
        
        if (intval($PublisherWebsiteListObj->PublisherWebsiteID) > 0):
        
            return $PublisherWebsiteListObj;
        endif;
        
        return null;
        
    }
    
    /**
     * Obtain an array of ad templates available from the database.
     * @return array An array of ad templates available, ID as the key and TemplateName as the value.
     */
    protected function get_ad_templates()
    {
        $AdTemplatesFactory = \_factory\AdTemplates::get_instance();
        $AdTemplatesObjList = array();
        $AdTemplatesParameters = array();
        $AdTemplateList = array('' => 'CUSTOM');
        
        $AdTemplatesObjList = $AdTemplatesFactory->get_object($AdTemplatesParameters);
        
        foreach ($AdTemplatesObjList as $TemplateItem):
        
        	$AdTemplateList[$TemplateItem->AdTemplateID] = $TemplateItem->TemplateName . ' (' . $TemplateItem->Width . ' x ' . $TemplateItem->Height . ')';
        endforeach;
        
        return $AdTemplateList;
        
    }
    
    /**
     * Display the Ad Zone available to act upon.
     * @return \Zend\View\Model\ViewModel
     */
    public function indexAction()
    {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
        
        $error_message = null;
        $ZoneList = array();
        $DomainID = intval($this->params()->fromRoute('param1', 0));
        
        $DomainObj = $this->get_domain_data($DomainID, $this->PublisherInfoID);
        
        if ($DomainObj === null):
        
            $error_message = "An invalid publishing web domain was specified for the specified user.";
            
        
        else: 
        
            $PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
            $parameters = array();
            
            // You must check both the DomainOwnerID to make sure
            // that the user does indeed own the entry; otherwise we have a security
            // problem. You also need to specify the PublisherAdZone for the PublisherWebsiteID, since both
            // Websites and Ads tables have PublisherWebsiteID as a column.
            $parameters["PublisherAdZone.PublisherWebsiteID"] = $DomainID;
            $parameters["PublisherWebsite.DomainOwnerID"] = $this->PublisherInfoID;
            
            try {
                $ZoneList = $PublisherAdZoneFactory->get_joined($parameters);
            }
            catch(\Exception $e)
            {
                $error_message = "A database error has occurred: " . $e->getMessage();
                $ZoneList = array();
            }
        endif;
        if ($this->is_super_admin || $this->is_domain_admin):
        
            $headers = array("#","Ad Zone Name","Status","Space Size","Floor Price","Total Requests","Impressions Filled","Total Revenue","Created","Updated","Action");
            $meta_data = array("AdName","AdStatus","AutoApprove","AdTemplateID","FloorPrice","TotalRequests","TotalImpressionsFilled","TotalAmount","DateCreated","DateUpdated");
        
        else:
        
            $headers = array("#","Ad Zone Name","Status","Space Size","Floor Price","Total Requests","Impressions Filled","Total Revenue","Created","Updated","Action");
            $meta_data = array("AdName","AdStatus","AutoApprove","AdTemplateID","FloorPrice","TotalRequests","TotalImpressionsFilled","TotalAmount","DateCreated","DateUpdated");
        endif;
        
        // TO DO: Permission issues.
        
        $view = new ViewModel(array(
        	'true_user_name' => $this->true_user_name,
            'zone_list_raw' => $ZoneList,
            'zone_list' => $this->order_data_table($meta_data,$ZoneList,$headers),
            'is_super_admin' => $this->is_super_admin,
        	'is_domain_admin' => $this->is_domain_admin,
            'user_id_list' => $this->user_id_list_publisher,
            'impersonate_id' => $this->ImpersonateID,
            'effective_id' => $this->EffectiveID,
            'domain_obj' => $DomainObj,
            'error_message' => $error_message,
        	'dashboard_view' => 'publisher',
	    	'user_identity' => $this->identity(),
        	'header_title' => '<a href="/publisher/zone/' . $DomainObj->PublisherWebsiteID . '/create">Create New Ad Zone</a>'
        ));
        
        return $view;
    }
    
    /**
     * Create a new ad space.
     * @return Ambigous <\Zend\Http\Response, \Zend\Stdlib\ResponseInterface>|multitype:Ambigous <NULL, string>  Ambigous <NULL, \DashboardManager\model\PublisherWebsite>
     */
    public function createAction()
    {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
        
        $error_message = null;

        $DomainID = intval($this->params()->fromRoute('param1', 0));
        
        $DomainObj = $this->get_domain_data($DomainID, $this->PublisherInfoID);
        
        if ($DomainObj === null):
        
        	$error_message = "An invalid publishing web domain was specified for the specified user.";
            
        elseif ($DomainObj->ApprovalFlag == 2):

        	$error_message = "This domain was rejected and you can not add any new zones.";
        
        endif;

        $request = $this->getRequest();
        
        if ($request->getPost("ImpressionType") == 'video'):
	         
	        $needed_input = array(
	        		'AdName',
	        		'Description',
	        		'MinDuration',
	        		'MaxDuration',
	        		'Mimes'
	        );
	        
	    else:
	        
		    $needed_input = array(
		    		'AdName',
		    		'AuctionType',
		    		'Description',
		    		'Width',
		    		'Height'
		    );
        
        endif;
		
        $AdTemplateList = $this->get_ad_templates();
        
        if ($request->isPost() && $DomainObj !== null && $error_message === null):
        
            $ad = new \model\PublisherAdZone();
            
            $validate = $this->validateInput($needed_input, false);
            
            if ($validate):
            
                $PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
                
                $ad->AdName					= strip_tags($request->getPost("AdName"));
                $ad->AuctionType			= 'rtb';
                $ad->HeaderBiddingAdUnitID	= null;
				$ad->Description	 		= strip_tags($request->getPost("Description"));
				$ad->PassbackAdTag 			= $request->getPost("PassbackAdTag");
				$floor_price 				= $request->getPost("FloorPrice") == null ? 0 : $request->getPost("FloorPrice");
				$ad->FloorPrice 			= floatval($floor_price);
				$ad->AdTemplateID 			= $request->getPost("AdTemplateID");
				$ad->IsMobileFlag 			= $request->getPost("IsMobileFlag");
				$ad->Width 					= $request->getPost("Width");
				$ad->Height 				= $request->getPost("Height");
				$ad->AdOwnerID				= $this->PublisherInfoID;

				if ($ad->AdOwnerID <= 0) die("No Auth");
				
				$ad->ImpressionType			= $request->getPost("ImpressionType") == 'video' ? 'video' : 'banner';
				
				if ($ad->ImpressionType == 'video'):
				
					$min_duration 				= $request->getPost("MinDuration");
						
					$max_duration 				= $request->getPost("MaxDuration");
					
					$mimes 						= $request->getPost("Mimes");
					if ($mimes && is_array($mimes) && count($mimes) > 0):
						$mimes = join(',', $mimes);
					else:
						$mimes = "";
					endif;
					
					$protocols 					= $request->getPost("Protocols");
					if ($protocols && is_array($protocols) && count($protocols) > 0):
						$protocols = join(',', $protocols);
					else:
						$protocols = "";
					endif;
					
					$apis_supported 			= $request->getPost("ApisSupported");
					if ($apis_supported && is_array($apis_supported) && count($apis_supported) > 0):
						$apis_supported = join(',', $apis_supported);
					else:
						$apis_supported = "";
					endif;
					
					$delivery 					= $request->getPost("Delivery");
					if ($delivery && is_array($delivery) && count($delivery) > 0):
						$delivery = join(',', $delivery);
					else:
						$delivery = "";
					endif;
					
					$playback 					= $request->getPost("Playback");
					if ($playback && is_array($playback) && count($playback) > 0):
						$playback = join(',', $playback);
					else:
						$playback = "";
					endif;
					
					$start_delay 				= $request->getPost("StartDelay");
					
					$linearity 					= $request->getPost("Linearity");
					
					$fold_pos 					= $request->getPost("FoldPos");
					
				endif;
				
				$linkedbanners = array();
				
				$auto_approve_zones = $this->config_handle['settings']['publisher']['auto_approve_zones'];
				$ad->AutoApprove = ($auto_approve_zones == true) ? 1 : 0;
				
				// Disapprove the changes if not admin.
				if ($this->is_super_admin || $this->is_domain_admin || $auto_approve_zones == true):
					$ad->AdStatus = 1;
				else:
					$ad->AdStatus = 0;
				endif;
				
				$rebuild_header_id = null;

                // Check to see if an entry exists with the same name for the same website domain. A NULL means there are no duplicates.
                if ($PublisherAdZoneFactory->get_row(array("PublisherWebsiteID" => $DomainObj->PublisherWebsiteID, "AdName" => $ad->AdName)) === null):
                
                    $ad->PublisherWebsiteID = $DomainObj->PublisherWebsiteID;
                          
                    if($ad->AdTemplateID != null) {
						$AdTemplatesFactory = \_factory\AdTemplates::get_instance();
						$AdTemplatesObj = $AdTemplatesFactory->get_row(array("AdTemplateID" => $ad->AdTemplateID));
						$ad->Width = $AdTemplatesObj->Width;
						$ad->Height = $AdTemplatesObj->Height;
					}
                    
                    
                    try {
                    	
	                    $publisher_ad_zone_id = $PublisherAdZoneFactory->save_ads($ad);
	                    
	                    $ad->PublisherAdZoneID											= $publisher_ad_zone_id;
	                    
	                    $auction_type													= strip_tags($request->getPost("AuctionType"));
	                    
	                    // If this publisher zone is for video save the extra table info
	                    if ($ad->ImpressionType == 'video'):
	                    
	                   	 	$PublisherAdZoneVideoFactory = \_factory\PublisherAdZoneVideo::get_instance();
	                    
	                    	$PublisherAdZoneVideo = new \model\PublisherAdZoneVideo();
	                    	$PublisherAdZoneVideo->PublisherAdZoneID 					= $publisher_ad_zone_id;
	                    	$PublisherAdZoneVideo->MimesCommaSeparated 					= $mimes;
	                    	$PublisherAdZoneVideo->MinDuration							= $min_duration;
	                    	$PublisherAdZoneVideo->MaxDuration							= $max_duration;
	                    	$PublisherAdZoneVideo->ProtocolsCommaSeparated				= $protocols;
	                    	$PublisherAdZoneVideo->ApisSupportedCommaSeparated			= $apis_supported;
	                    	$PublisherAdZoneVideo->DeliveryCommaSeparated				= $delivery;
	                    	$PublisherAdZoneVideo->PlaybackCommaSeparated				= $playback;
	                    	$PublisherAdZoneVideo->StartDelay							= $start_delay;
	                    	$PublisherAdZoneVideo->Linearity							= $linearity;
	                    	$PublisherAdZoneVideo->FoldPos								= $fold_pos;
	                    	$PublisherAdZoneVideo->DateCreated							= date("Y-m-d H:i:s");

	                    	$PublisherAdZoneVideoFactory->savePublisherAdZoneVideo($PublisherAdZoneVideo);
	                    	
	                    elseif ($auction_type == 'header'):

	                   	 	$page_header_id		 										= $request->getPost("PageHeaderID");
	                    
		                    $header_bidding_type 										= $request->getPost("HeaderBiddingType");
		                    
		                    $HeaderBiddingPageFactory = \_factory\HeaderBiddingPage::get_instance();
		                    $HeaderBiddingAdUnitFactory = \_factory\HeaderBiddingAdUnit::get_instance();
		                    
		                    $params = array();
		                    $params["PageName"] 										= $page_header_id;
		                    $params["PublisherWebsiteID"] 								= $DomainObj->PublisherWebsiteID;
		                    $HeaderBiddingPage											= $HeaderBiddingPageFactory->get_row($params);

		                    if ($HeaderBiddingPage == null):
			                    	
			                    $HeaderBiddingPage 										= new \model\HeaderBiddingPage();
			                    $HeaderBiddingPage->PublisherWebsiteID 					= $DomainObj->PublisherWebsiteID;
			                    $HeaderBiddingPage->PageName 							= $page_header_id;
			                    $HeaderBiddingPage->JSHeaderFileUnqName 				= \util\ZoneHelper::unique_js_filename() . '-'. \util\ZoneHelper::unique_js_filename() . '.js';
			                    $HeaderBiddingPage->DateCreated							= date("Y-m-d H:i:s");
			                    	
			                    $HeaderBiddingPage->HeaderBiddingPageID 				= $HeaderBiddingPageFactory->saveHeaderBiddingPage($HeaderBiddingPage);

		                    endif;

		                    if ($HeaderBiddingPage->HeaderBiddingPageID):

			                    $ad->HeaderBiddingPageID								= $HeaderBiddingPage->HeaderBiddingPageID;
		                    
		            			$rebuild_header_id										= $ad->HeaderBiddingPageID;
		                    
			                    $ad->AuctionType										= 'header';

			                    $PublisherAdZoneFactory->save_ads($ad);
			                    
		    					$post = $this->getRequest()->getPost();                
	
		                    	/* 
		                    	 * _NEW_ for new header bidding items which are being created
		                    	 * existing header bidding items are deleted each time in an update
		                    	 */
		                    	$key_type = '_NEW_';
		                    
		                    	// returns an array of new header bidding item ids
		                    	$new_keys = \util\ZoneHelper::getHeaderBiddingKeys($post, $key_type);
		                    	
		                    	// returns an associative array of new header bidding items id => type
		                    	$header_bidding_types = \util\ZoneHelper::getHeaderBiddingTypes($request, $new_keys, $key_type);
		                    	
		                    	// returns an associative array of new header bidding type => array of header bidding of that type
		                    	$header_bidding_items_new = \util\ZoneHelper::getHeaderBiddingItems($request, $ad, $ad->HeaderBiddingAdUnitID, $header_bidding_types, $key_type);

								foreach ($header_bidding_items_new as $bidder_type => $header_bidding_items_list):
									
									foreach ($header_bidding_items_list as $header_bidding_item):
									
										$HeaderBiddingAdUnit = new \model\HeaderBiddingAdUnit();
										$HeaderBiddingAdUnit->HeaderBiddingPageID 						= $HeaderBiddingPage->HeaderBiddingPageID;
										$HeaderBiddingAdUnit->PublisherAdZoneID 						= $header_bidding_item["PublisherAdZoneID"];
										$HeaderBiddingAdUnit->AdExchange 								= $bidder_type;
										$HeaderBiddingAdUnit->DivID		 								= $header_bidding_item["DivID"];
										$HeaderBiddingAdUnit->Height		 							= $header_bidding_item["Height"];
										$HeaderBiddingAdUnit->Width		 								= $header_bidding_item["Width"];
										$HeaderBiddingAdUnit->CustomParams		 						= $header_bidding_item["CustomParams"];
										$HeaderBiddingAdUnit->AdTag		 								= $header_bidding_item["AdTag"];
										$HeaderBiddingAdUnit->DateCreated								= date("Y-m-d H:i:s");

										$header_bidding_ad_unit_id = $HeaderBiddingAdUnitFactory->saveHeaderBiddingAdUnit($HeaderBiddingAdUnit);

									endforeach;
									
								endforeach;

							endif;
	                    	
	                    endif;

	                    if ($rebuild_header_id != null):
	                    
	                    	\util\HeaderBiddingHelper::rebuild_header_bidder($this->config_handle, $rebuild_header_id);
	                     
	                    endif;
	                    
	                    if ($this->config_handle['mail']['subscribe']['zones'] === true):
		                
			                $is_approved = $ad->AdStatus == 1 ? 'Yes' : 'No';
		                	$is_mobile = $ad->IsMobileFlag == 1 ? 'Yes' : 'No';
		                	
			                // if this zone was not created by the admin, then send out a notification email
			                $message = '<b>New NginAd Publisher Zone Added by ' . $this->true_user_name . '.</b><br /><br />';
			                $message = $message.'<table border="0" width="10%">';
			                $message = $message.'<tr><td><b>WebDomain: </b></td><td>'.$DomainObj->WebDomain.'</td></tr>';
			                $message = $message.'<tr><td><b>AdName: </b></td><td>'.$ad->AdName.'</td></tr>';
			                $message = $message.'<tr><td><b>Description: </b></td><td>'.$ad->Description.'</td></tr>';
			                $message = $message.'<tr><td><b>PassbackAdTag: </b></td><td>'.$ad->PassbackAdTag.'</td></tr>';
			                $message = $message.'<tr><td><b>FloorPrice: </b></td><td>'.$ad->FloorPrice.'</td></tr>';
			                $message = $message.'<tr><td><b>IsMobile: </b></td><td>'.$is_mobile.'</td></tr>';
			                $message = $message.'<tr><td><b>FloorPrice: </b></td><td>'.$ad->FloorPrice.'</td></tr>';
			                $message = $message.'<tr><td><b>Ad Tag Size: </b></td><td>'.$ad->Width.'x'.$ad->Height.'</td></tr>';
			                $message = $message.'<tr><td><b>AdOwnerID: </b></td><td>'.$ad->AdOwnerID.'</td></tr>';
			                $message = $message.'<tr><td><b>ImpressionType: </b></td><td>'.$ad->ImpressionType.'</td></tr>';
			                $message = $message.'<tr><td><b>Approved: </b></td><td>'.$is_approved.'</td></tr>';
			                $message = $message.'</table>';
			                
			                $subject = "New NginAd Publisher Zone Added by " . $this->true_user_name;
			                	
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
	                    
	                    return $this->redirect()->toRoute('publisher/zone',array('param1' => $DomainObj->PublisherWebsiteID));
                    }
                    catch(\Zend\Db\Adapter\Exception\InvalidQueryException $e) {
                        $error_message ="ERROR " . $e->getCode().  ": A database error has occurred, please contact customer service.";
                        $error_message .= "Details: " . $e->getMessage();
                    }
                
                else: 
                
                    $error_message = "ERROR: An ad with the name \"" . $ad->AdName . "\" already exists for the domain \"" . $DomainObj->WebDomain . "\"."; 
                endif;
            
            else:
            
                $error_message = "ERROR: Required fields are not filled in or invalid input.";
            endif;
            
        
        else:
        
             // If first coming to this form, set the Ad Owner ID.
        endif;
        
        $current_header_bidding_pagename_list 	= "";
        $current_fold_pos = "";
        $current_linearity = "";
        $current_start_delay = "0";
        $current_playback_methods = array();
        $current_delivery_methods = array();
        $current_apis_supported = array();
        $current_protocols = array();
        $current_mimes = array();
        
        
        $HeaderBiddingPageFactory 								= \_factory\HeaderBiddingPage::get_instance();
        $params = array();
        $params["PublisherWebsiteID"] 							= $DomainObj->PublisherWebsiteID;
        $current_header_bidding_pagename_list					= $HeaderBiddingPageFactory->get($params);

        return array(
        		'error_message' => $error_message,
        		'is_super_admin' => $this->is_super_admin,
        		'user_id_list' => $this->user_id_list_publisher,
        		'effective_id' => $this->EffectiveID,
        		'impersonate_id' => $this->ImpersonateID,
                'domain_obj' => $DomainObj,
        		'true_user_name' => $this->true_user_name,
        		'dashboard_view' => 'publisher',
        		'AdOwnerID' => $this->PublisherInfoID,
        		'AdTemplateList' => $this->get_ad_templates(),
	    		'user_identity' => $this->identity(),
        		'header_title' => 'Create New Ad Zone',
        		
        		'fold_pos' => \util\BannerOptions::$fold_pos,
        		'linearity' => \util\BannerOptions::$linearity,
        		'start_delay' => \util\BannerOptions::$start_delay,
        		'playback_methods' => \util\BannerOptions::$playback_methods,
        		'delivery_methods' => \util\BannerOptions::$delivery_methods,
        		'apis_supported' => \util\BannerOptions::$apis_supported,
        		'protocols' => \util\BannerOptions::$protocols,
        		'mimes' => \util\BannerOptions::$mimes,
        		'header_bidding_adxs_list' => \util\ZoneHelper::$header_bidding_adxs,
        		'header_bidding_providers' => \util\ZoneHelper::$header_bidding_providers,
        		'current_header_bidding_pagename_list' => $current_header_bidding_pagename_list,
        		
        		'current_fold_pos' => $current_fold_pos,
        		'current_linearity' => $current_linearity,
        		'current_start_delay' => $current_start_delay,
        		'current_playback_methods' => $current_playback_methods,
        		'current_delivery_methods' => $current_delivery_methods,
        		'current_apis_supported' => $current_apis_supported,
        		'current_protocols' => $current_protocols,
        		'current_mimes' => $current_mimes
        		
        );
    }
    
    /**
     * Edit existing ad space.
     * @return Ambigous <\Zend\Http\Response, \Zend\Stdlib\ResponseInterface>|multitype:Ambigous <NULL, string>  Ambigous <NULL, \DashboardManager\model\PublisherWebsite> Ambigous <number, NULL>
     */
    public function editAction()
    {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
        $error_message = null;
        
        $DomainID = intval($this->params()->fromRoute('param1', 0));
        $PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
        $InsertionOrderLineItemFactory = \_factory\InsertionOrderLineItem::get_instance();
        $PublisherAdZoneVideoFactory = \_factory\PublisherAdZoneVideo::get_instance();
        $HeaderBiddingPageFactory = \_factory\HeaderBiddingPage::get_instance();
        $HeaderBiddingAdUnitFactory = \_factory\HeaderBiddingAdUnit::get_instance();
        
        $editResultObj = new \model\PublisherAdZone();
        
        $DomainObj = $this->get_domain_data($DomainID, $this->PublisherInfoID);
        
        if ($DomainObj === null):
        
        	$error_message = "An invalid publishing web domain was specified for the specified user.";
        
        elseif ($DomainObj->ApprovalFlag == 2):
        
        	$error_message = "This domain was rejected and you can not edit this zone.";
        
        else:
        
        	$request = $this->getRequest();
        
        	if ($request->getPost("ImpressionType") == 'video'):
        	
	        	$needed_input = array(
	        			'AdName',
	        			'Description',
	        			'MinDuration',
	        			'MaxDuration',
	        			'Mimes'
	        	);
	        	
        	else:
        
	        	$needed_input = array(
	        			'AdName',
	        			'AuctionType',
	        			'Description',
	        			'Width',
	        			'Height'
	        	);
            
            endif;
		
            $linkedbanners = array();
            
            $AdTemplateList = $this->get_ad_templates();

            $current_mimes 					= \util\BannerOptions::$mimes;
            
            $current_min_duration 			= "0";
            $current_max_duration 			= "1000";
            
            $current_apis_supported 		= array();
            $current_protocols 				= array();
            $current_delivery_methods 		= array();
            $current_playback_methods 		= array();
            
            $current_start_delay 			= "";
            $current_linearity 				= "";
            $current_fold_pos 				= "";

            $current_header_bidding_json 	= "";
            
            $rebuild_previous_header_id = null;
            $rebuild_header_id = null;
            
            $current_header_bidding_pagename 		= "";
            $current_header_bidding_pagename_list 	= "";
            $current_page_header_id		 			= "";
            
            $current_auction_type					= "rtb";
            
            $current_header_bidding_json 			= "";
            
            // Make sure the value provided is valid.
            $AdSpaceID = intval($this->params()->fromRoute('id', 0));

            if ($AdSpaceID > 0):
            
                $AdSpaceParameters = array("PublisherWebsiteID" => $DomainObj->PublisherWebsiteID, "PublisherAdZoneID" => $AdSpaceID);
                $editResultObj = $PublisherAdZoneFactory->get_row_object($AdSpaceParameters);

                if (intval($editResultObj->PublisherAdZoneID) == $AdSpaceID && intval($editResultObj->PublisherWebsiteID) == $DomainObj->PublisherWebsiteID):

	                $params = array();
	                $params['PublisherAdZoneID'] 	= $editResultObj->PublisherAdZoneID;
	                
	                $PublisherAdZoneVideo 			= $PublisherAdZoneVideoFactory->get_row($params);

	                if ($PublisherAdZoneVideo != null):
	                
		                $current_mimes 					= explode(',', $PublisherAdZoneVideo->MimesCommaSeparated);
		                
	                	if (!$PublisherAdZoneVideo->MimesCommaSeparated || !count($current_mimes)):
	                		$current_mimes = \util\BannerOptions::$mimes;
	                	endif;
	                
		                $current_min_duration 			= $PublisherAdZoneVideo->MinDuration;
		                $current_max_duration 			= $PublisherAdZoneVideo->MaxDuration;
		                
		                $current_apis_supported 		= explode(',', $PublisherAdZoneVideo->ApisSupportedCommaSeparated);
		                $current_protocols 				= explode(',', $PublisherAdZoneVideo->ProtocolsCommaSeparated);
		                $current_delivery_methods 		= explode(',', $PublisherAdZoneVideo->DeliveryCommaSeparated);
		                $current_playback_methods 		= explode(',', $PublisherAdZoneVideo->PlaybackCommaSeparated);
		                
		                $current_start_delay 			= $PublisherAdZoneVideo->StartDelay;
		                $current_linearity 				= $PublisherAdZoneVideo->Linearity;
		                $current_fold_pos 				= $PublisherAdZoneVideo->FoldPos;

		                if ($current_min_duration == "" || $current_min_duration == null):
		                	$current_min_duration = 0;
		                endif;
		                
		                if ($current_max_duration == "" || $current_max_duration == null):
		                	$current_max_duration = 1000;
		                endif;
		                
		            endif;
		            
		        	$params = array();
		       		$params["PublisherAdZoneID"] 								= $editResultObj->PublisherAdZoneID;
		     		$HeaderBiddingAdUnit										= $HeaderBiddingAdUnitFactory->get_row($params);
		            
		            if ($HeaderBiddingAdUnit != null):
			            
		            	$current_auction_type									= $editResultObj->AuctionType;
		            
			            $params = array();
			            $params["HeaderBiddingPageID"] 							= $HeaderBiddingAdUnit->HeaderBiddingPageID;
			            $HeaderBiddingPage										= $HeaderBiddingPageFactory->get_row($params);
		            
			            $current_page_header_id		 							= $HeaderBiddingAdUnit->HeaderBiddingPageID;
			            $current_header_bidding_pagename 						= $HeaderBiddingPage->PageName;
			            
			            $params = array();
			            $params["PublisherWebsiteID"] 							= $editResultObj->PublisherWebsiteID;
			            $current_header_bidding_pagename_list					= $HeaderBiddingPageFactory->get($params);

			            $params 							= array();
			            $params["PublisherAdZoneID"] 		= $editResultObj->PublisherAdZoneID;
			             
			            $HeaderBiddingAdUnitList 			= $HeaderBiddingAdUnitFactory->get($params);
			            
			            foreach ($HeaderBiddingAdUnitList as $key => $HeaderBiddingAdUnit):
			            	
			            	if ($HeaderBiddingAdUnitList[$key]['CustomParams'] != null):
			            		$HeaderBiddingAdUnitList[$key]['CustomParams'] = unserialize($HeaderBiddingAdUnitList[$key]['CustomParams']);
			            	endif;
			            	
			            endforeach;
			            
			            $current_header_bidding_json 		= json_encode($HeaderBiddingAdUnitList);
			             
		            endif;

		            $auction_type							= strip_tags($request->getPost("AuctionType"));
		            
                    if ($request->isPost()):
                
                    	$validate = $this->validateInput($needed_input, false);
            
            			if ($validate):        			

	                    	$editResultObj->AdName 					= strip_tags($request->getPost("AdName"));
            				$editResultObj->AuctionType 			= strip_tags($request->getPost("AuctionType"));
            				$editResultObj->HeaderBiddingPageID 	= strip_tags($request->getPost("HeaderBiddingPageID"));
							$editResultObj->Description 			= strip_tags($request->getPost("Description"));
							$editResultObj->PassbackAdTag 			= $request->getPost("PassbackAdTag");
							$floor_price 							= $request->getPost("FloorPrice") == null ? 0 : $request->getPost("FloorPrice");
							$editResultObj->FloorPrice 				= floatval($floor_price);
							$editResultObj->AdTemplateID 			= $request->getPost("AdTemplateID");
							$editResultObj->IsMobileFlag 			= $request->getPost("IsMobileFlag");
							$editResultObj->Width 					= $request->getPost("Width");
							$editResultObj->Height 					= $request->getPost("Height");

							if ($editResultObj->HeaderBiddingPageID != null):
							
								$rebuild_previous_header_id 		= $editResultObj->HeaderBiddingPageID;
							
							endif;
							
							$auto_approve_zones = $this->config_handle['settings']['publisher']['auto_approve_zones'];
							$editResultObj->AutoApprove = ($auto_approve_zones == true) ? 1 : 0;
							
                    	    // Disapprove the changes if not admin.
                            if ($this->is_super_admin || $auto_approve_zones == true):
                                $editResultObj->AdStatus = 1;
                            else:
                            	$editResultObj->AdStatus = 0;
                            endif;
                    	    
                			$editResultObj->PublisherWebsiteID = $DomainObj->PublisherWebsiteID;
                			if($editResultObj->AdTemplateID != null):
								$AdTemplatesFactory = \_factory\AdTemplates::get_instance();
								$AdTemplatesObj = $AdTemplatesFactory->get_row(array("AdTemplateID" => $editResultObj->AdTemplateID));
								$editResultObj->Width = $AdTemplatesObj->Width;
								$editResultObj->Height = $AdTemplatesObj->Height;
							endif;
                			
							$editResultObj->ImpressionType				= $request->getPost("ImpressionType") == 'video' ? 'video' : 'banner';
							
							if ($editResultObj->ImpressionType == 'video'):
							
								$editResultObj->AdTemplateID = null;
							
								$min_duration 				= $request->getPost("MinDuration");
								
								$max_duration 				= $request->getPost("MaxDuration");
									
								$mimes 						= $request->getPost("Mimes");
								if ($mimes && is_array($mimes) && count($mimes) > 0):
									$mimes = join(',', $mimes);
								endif;
									
								$protocols 					= $request->getPost("Protocols");
								if ($protocols && is_array($protocols) && count($protocols) > 0):
									$protocols = join(',', $protocols);
								endif;
									
								$apis_supported 			= $request->getPost("ApisSupported");
								if ($apis_supported && is_array($apis_supported) && count($apis_supported) > 0):
									$apis_supported = join(',', $apis_supported);
								endif;
									
								$delivery 					= $request->getPost("Delivery");
								if ($delivery && is_array($delivery) && count($delivery) > 0):
									$delivery = join(',', $delivery);
								endif;
									
								$playback 					= $request->getPost("Playback");
								if ($playback && is_array($playback) && count($playback) > 0):
									$playback = join(',', $playback);
								endif;
									
								$start_delay 				= $request->getPost("StartDelay");
									
								$linearity 					= $request->getPost("Linearity");
									
								$fold_pos 					= $request->getPost("FoldPos");
									
								$editResultObj->HeaderBiddingPageID = null;
								$HeaderBiddingAdUnitFactory->deleteHeaderBiddingAdUnitByPublisherAdZoneID($editResultObj->PublisherAdZoneID);
								
	                    elseif ($auction_type == 'header'):

	                   	 	$page_header_id		 										= $request->getPost("PageHeaderID");
	                    
		                    $header_bidding_type 										= $request->getPost("HeaderBiddingType");

		                    $params = array();
		                    $params["PageName"] 										= $page_header_id;
		                    $params["PublisherWebsiteID"] 								= $DomainObj->PublisherWebsiteID;
		                    $HeaderBiddingPage											= $HeaderBiddingPageFactory->get_row($params);
		                    
		                    if ($HeaderBiddingPage == null):
			                    	
			                    $HeaderBiddingPage 										= new \model\HeaderBiddingPage();
			                    $HeaderBiddingPage->PublisherWebsiteID 					= $DomainObj->PublisherWebsiteID;
			                    $HeaderBiddingPage->PageName 							= $page_header_id;
			                    $HeaderBiddingPage->JSHeaderFileUnqName 				= \util\ZoneHelper::unique_js_filename() . '-' . \util\ZoneHelper::unique_js_filename() . '.js';
			                    $HeaderBiddingPage->DateCreated							= date("Y-m-d H:i:s");
			                    	
			                    $HeaderBiddingPage->HeaderBiddingPageID 				= $HeaderBiddingPageFactory->saveHeaderBiddingPage($HeaderBiddingPage);

		                    endif;
		                    
		                    if ($HeaderBiddingPage->HeaderBiddingPageID):

		                    	$editResultObj->HeaderBiddingPageID = $HeaderBiddingPage->HeaderBiddingPageID;
		                    	/*
		                    	 * Last header associated to this publisher ad zone was different
		                    	 * so recompile the header bidding code for the page header
		                    	 */
		                    	if ($editResultObj->HeaderBiddingPageID != $HeaderBiddingPage->HeaderBiddingPageID):
		                    		
		                    		$rebuild_previous_header_id = $editResultObj->HeaderBiddingPageID;
		                    		
		                    	endif;
		                    
			                    $editResultObj->AuctionType								= 'header';
			                    
		    					$post = $this->getRequest()->getPost();                
	
		                    	/* 
		                    	 * _NEW_ for new header bidding items which are being created
		                    	 * existing header bidding items are deleted each time in an update
		                    	 */
		                    	$key_type = '_NEW_';
		                    
		                    	// returns an array of new header bidding item ids
		                    	$new_keys = \util\ZoneHelper::getHeaderBiddingKeys($post, $key_type);
		                    	
		                    	// returns an associative array of new header bidding items id => type
		                    	$header_bidding_types = \util\ZoneHelper::getHeaderBiddingTypes($request, $new_keys, $key_type);
		                    	
		                    	// returns an associative array of new header bidding type => array of header bidding of that type
		                    	$header_bidding_items_new = \util\ZoneHelper::getHeaderBiddingItems($request, $editResultObj, $editResultObj->HeaderBiddingPageID, $header_bidding_types, $key_type);

		                    	$rebuild_header_id = $HeaderBiddingPage->HeaderBiddingPageID;
		                    	
		                    	$header_bidding_ad_unit_id_list = array();
		                    	
		                    	foreach ($header_bidding_items_new as $bidder_type => $header_bidding_items_list):
		                    		
		                    		foreach ($header_bidding_items_list as $header_bidding_item):
		                    			
		                    			$header_bidding_ad_unit_id_list[] = $header_bidding_item['HeaderBiddingAdUnitID'];
		                    			
		                    		endforeach;
		                    			
		                    	endforeach;
		                    	
		                    	/* Start: DELETE HeaderBiddingAdUnits no longer being used */
		                    	
		                    	$header_bidding_ad_unit_to_delete_id_list = array();
		                    	
		                    	$params = array();
		                    	$params["PublisherAdZoneID"]		= $editResultObj->PublisherAdZoneID;
		                    	$HeaderBiddingAdUnitList 			= $HeaderBiddingAdUnitFactory->get($params);
		                    	
		                    	foreach ($HeaderBiddingAdUnitList as $HeaderBiddingAdUnit):
		                    			
		                    		if (!in_array($HeaderBiddingAdUnit->HeaderBiddingAdUnitID, $header_bidding_ad_unit_id_list)):
		                    			
		                    			$header_bidding_ad_unit_to_delete_id_list[] = $HeaderBiddingAdUnit->HeaderBiddingAdUnitID;
		                    			
		                    		endif;
		                    		
		                    	endforeach;
		                    	
		                    	foreach ($header_bidding_ad_unit_to_delete_id_list as $header_bidding_ad_unit_to_delete_id):
		                    	
		                    		$HeaderBiddingAdUnitFactory->deleteHeaderBiddingAdUnit($header_bidding_ad_unit_to_delete_id);
		                    	
		                    	endforeach;
		                    	
		                    	/* End: DELETE HeaderBiddingAdUnits no longer being used */
		                    	
								foreach ($header_bidding_items_new as $bidder_type => $header_bidding_items_list):
									
									foreach ($header_bidding_items_list as $header_bidding_item):

										if ($header_bidding_item['HeaderBiddingAdUnitID'] != 'new'):
									
											$params = array();
											$params['HeaderBiddingAdUnitID'] 	= $header_bidding_item['HeaderBiddingAdUnitID'];
											$HeaderBiddingAdUnit 				= $HeaderBiddingAdUnitFactory->get_row($params);
										
											if ($HeaderBiddingAdUnit != null):
												
												$HeaderBiddingAdUnit = new \model\HeaderBiddingAdUnit();
												$HeaderBiddingAdUnit->HeaderBiddingAdUnitID 					= $header_bidding_item['HeaderBiddingAdUnitID'];;
												$HeaderBiddingAdUnit->HeaderBiddingPageID 						= $HeaderBiddingPage->HeaderBiddingPageID;
												$HeaderBiddingAdUnit->PublisherAdZoneID 						= $header_bidding_item["PublisherAdZoneID"];
												$HeaderBiddingAdUnit->AdExchange 								= $bidder_type;
												$HeaderBiddingAdUnit->DivID		 								= $header_bidding_item["DivID"];
												$HeaderBiddingAdUnit->Height		 							= $header_bidding_item["Height"];
												$HeaderBiddingAdUnit->Width		 								= $header_bidding_item["Width"];
												$HeaderBiddingAdUnit->CustomParams		 						= $header_bidding_item["CustomParams"];
												$HeaderBiddingAdUnit->AdTag		 								= $header_bidding_item["AdTag"];
												$HeaderBiddingAdUnit->DateCreated								= date("Y-m-d H:i:s");	
													
											else:
											
												// error
												continue;
												
											endif;
										
										else:
											
											$HeaderBiddingAdUnit = new \model\HeaderBiddingAdUnit();
											$HeaderBiddingAdUnit->HeaderBiddingPageID 						= $HeaderBiddingPage->HeaderBiddingPageID;
											$HeaderBiddingAdUnit->PublisherAdZoneID 						= $header_bidding_item["PublisherAdZoneID"];
											$HeaderBiddingAdUnit->AdExchange 								= $bidder_type;
											$HeaderBiddingAdUnit->DivID		 								= $header_bidding_item["DivID"];
											$HeaderBiddingAdUnit->Height		 							= $header_bidding_item["Height"];
											$HeaderBiddingAdUnit->Width		 								= $header_bidding_item["Width"];
											$HeaderBiddingAdUnit->CustomParams		 						= $header_bidding_item["CustomParams"];
											$HeaderBiddingAdUnit->AdTag		 								= $header_bidding_item["AdTag"];
											$HeaderBiddingAdUnit->DateCreated								= date("Y-m-d H:i:s");
												
										endif;
										
										$header_bidding_ad_unit_id = $HeaderBiddingAdUnitFactory->saveHeaderBiddingAdUnit($HeaderBiddingAdUnit);

									endforeach;
									
								endforeach;

							endif;
	                    	
						else:
						
							$editResultObj->HeaderBiddingPageID = null;
							$HeaderBiddingAdUnitFactory->deleteHeaderBiddingAdUnitByPublisherAdZoneID($editResultObj->PublisherAdZoneID);
							
	                    endif;
							
                			try {
                				
                				$PublisherAdZoneFactory->save_ads($editResultObj);

                				// If this publisher zone is for video save the extra table info
                				if ($editResultObj->ImpressionType == 'video'):
	                				 
	                				$PublisherAdZoneVideo = new \model\PublisherAdZoneVideo();
	                				$PublisherAdZoneVideo->PublisherAdZoneID 			= $editResultObj->PublisherAdZoneID;
	                				$PublisherAdZoneVideo->MimesCommaSeparated 			= $mimes;
	                				$PublisherAdZoneVideo->MinDuration					= $min_duration;
	                				$PublisherAdZoneVideo->MaxDuration					= $max_duration;
	                				$PublisherAdZoneVideo->ApisSupportedCommaSeparated	= $apis_supported;
	                				$PublisherAdZoneVideo->ProtocolsCommaSeparated		= $protocols;
	                				$PublisherAdZoneVideo->DeliveryCommaSeparated		= $delivery;
	                				$PublisherAdZoneVideo->PlaybackCommaSeparated		= $playback;
	                				$PublisherAdZoneVideo->StartDelay					= $start_delay;
	                				$PublisherAdZoneVideo->Linearity					= $linearity;
	                				$PublisherAdZoneVideo->FoldPos						= $fold_pos;
	                				$PublisherAdZoneVideo->DateCreated					= date("Y-m-d H:i:s");
	                				
	                				/*
	                				 * Create a new entry each time since video is optional
	                				 */
	                				$PublisherAdZoneVideoFactory->savePublisherAdZoneVideo($PublisherAdZoneVideo);

	                			endif;

                				if ($rebuild_previous_header_id != null):
                				 
                					\util\HeaderBiddingHelper::rebuild_header_bidder($this->config_handle, $rebuild_previous_header_id);
                				 
                				endif;
                				
                				if ($auction_type == 'header' && $rebuild_header_id != null):
                				
                					\util\HeaderBiddingHelper::rebuild_header_bidder($this->config_handle, $rebuild_header_id);
                				
                				endif;
                				
                				if ($this->config_handle['mail']['subscribe']['zones'] === true):
	                			
	                				$is_approved = $editResultObj->AdStatus == 1 ? 'Yes' : 'No';
	                				$is_mobile = $editResultObj->IsMobileFlag == 1 ? 'Yes' : 'No';
	                			
		                			// if this zone was not created by the admin, then send out a notification email
		                			$message = '<b>New NginAd Publisher Zone Edited by ' . $this->true_user_name . '.</b><br /><br />';
		                			$message = $message.'<table border="0" width="10%">';
		                			$message = $message.'<tr><td><b>WebDomain: </b></td><td>'.$DomainObj->WebDomain.'</td></tr>';
		                			$message = $message.'<tr><td><b>AdName: </b></td><td>'.$editResultObj->AdName.'</td></tr>';
		                			$message = $message.'<tr><td><b>Description: </b></td><td>'.$editResultObj->Description.'</td></tr>';
		                			$message = $message.'<tr><td><b>PassbackAdTag: </b></td><td>'.$editResultObj->PassbackAdTag.'</td></tr>';
		                			$message = $message.'<tr><td><b>FloorPrice: </b></td><td>'.$editResultObj->FloorPrice.'</td></tr>';
		                			$message = $message.'<tr><td><b>IsMobile: </b></td><td>'.$is_mobile.'</td></tr>';
		                			$message = $message.'<tr><td><b>FloorPrice: </b></td><td>'.$editResultObj->FloorPrice.'</td></tr>';
		                			$message = $message.'<tr><td><b>Ad Tag Size: </b></td><td>'.$editResultObj->Width.'x'.$editResultObj->Height.'</td></tr>';
		                			$message = $message.'<tr><td><b>AdOwnerID: </b></td><td>'.$editResultObj->AdOwnerID.'</td></tr>';
		                			$message = $message.'<tr><td><b>ImpressionType: </b></td><td>'.$editResultObj->ImpressionType.'</td></tr>';
		                			$message = $message.'<tr><td><b>Approved: </b></td><td>'.$is_approved.'</td></tr>';
		                			$message = $message.'</table>';
		                			
		                			$subject = "New NginAd Publisher Zone Edited by " . $this->true_user_name;
		                			
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
                				
                				return $this->redirect()->toRoute('publisher/zone',array('param1' => $DomainObj->PublisherWebsiteID));
                			}
                			catch(\Zend\Db\Adapter\Exception\InvalidQueryException $e) {
                				$error_message ="ERROR " . $e->getCode().  ": A database error has occurred, please contact customer service.";
                				$error_message .= "Details: " . $e->getMessage();
                			}
                    	
                    	else:
                    	
                    		$error_message = "ERROR: Required fields are not filled in or invalid input.";
                    	endif;
                    
                    
                    else:
                    
                        //OK Display edit.
                    endif;
                    
                
                else:
                
                    $error_message = "An invalid Ad Zone ID was provided.";
                endif;
                
            
            else: 
            
                $error_message = "An invalid Ad Zone ID was provided.";
            endif;
        endif;

        return array(
        		'error_message' => $error_message,
        		'is_super_admin' => $this->is_super_admin,
        		'user_id_list' => $this->user_id_list_publisher,
        		'effective_id' => $this->EffectiveID,
        		'impersonate_id' => $this->ImpersonateID,
        		'domain_obj' => $DomainObj,
        		'editResultObj' => $editResultObj,
        		'AdTemplateList' => $this->get_ad_templates(),
        		'true_user_name' => $this->true_user_name,
        		'dashboard_view' => 'publisher',
	    		'user_identity' => $this->identity(),
        		'header_title' => 'Edit Ad Zone',
        		
        		'fold_pos' => \util\BannerOptions::$fold_pos,
        		'linearity' => \util\BannerOptions::$linearity,
        		'start_delay' => \util\BannerOptions::$start_delay,
        		'playback_methods' => \util\BannerOptions::$playback_methods,
        		'delivery_methods' => \util\BannerOptions::$delivery_methods,
        		'apis_supported' => \util\BannerOptions::$apis_supported,
        		'protocols' => \util\BannerOptions::$protocols,
        		'mimes' => \util\BannerOptions::$mimes,
        		
        		'current_mimes' => $current_mimes,
        		
        		'current_min_duration' => $current_min_duration,
        		'current_max_duration' => $current_max_duration,

        		'header_bidding_adxs_list' => \util\ZoneHelper::$header_bidding_adxs,
        		'header_bidding_providers' => \util\ZoneHelper::$header_bidding_providers,
        		
        		'current_auction_type' => $current_auction_type,
        		'current_header_bidding_pagename_list' => $current_header_bidding_pagename_list,
        		'current_header_bidding_pagename' => $current_header_bidding_pagename,
        		'current_header_bidding_json' => $current_header_bidding_json,

        		'current_apis_supported' => $current_apis_supported,
        		'current_protocols' => $current_protocols,
        		'current_delivery_methods' => $current_delivery_methods,
        		'current_playback_methods' => $current_playback_methods,
        		'current_start_delay' => $current_start_delay,
        		'current_linearity' => $current_linearity,
        		'current_fold_pos' => $current_fold_pos
        		
        );
    }
    
    public function deleteAction()
    {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
        $error_message = null;
        $DomainID = intval($this->params()->fromRoute('param1', 0));
        $PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
        $PublisherAdZoneVideoFactory = \_factory\PublisherAdZoneVideo::get_instance();
        
        $DomainObj = $this->get_domain_data($DomainID, $this->PublisherInfoID);
        $success = false;
        
        if ($DomainObj === null):
        
        	$error_message = "An invalid publishing web domain was specified for the specified user.";
        
        
        else:
        
            $AdTemplateList = $this->get_ad_templates();
            $request = $this->getRequest();
            
            // Make sure the value provided is valid.
            $AdSpaceID = intval($this->params()->fromRoute('id', 0));
            
            if ($AdSpaceID > 0):
            
            	$AdSpaceParameters = array("PublisherWebsiteID" => $DomainObj->PublisherWebsiteID, "PublisherAdZoneID" => $AdSpaceID);
            	$deleteCheckResultObj = $PublisherAdZoneFactory->get_row_object($AdSpaceParameters);
            	
	           	//if (intval($deleteCheckResultObj->PublisherAdZoneID) == $AdSpaceID && intval($deleteCheckResultObj->PublisherWebsiteID) == $DomainObj->PublisherWebsiteID):
 
            		if ($request->isPost()): 
            		
            		    if ($request->getPost('del', 'No') == 'Yes'):
            		    
            		    	// Is this user allowed to delete this entry?
            		    	if ($this->is_super_admin || $DomainObj->DomainOwnerID == $this->PublisherInfoID):
            		    	
            		    		if (intval($PublisherAdZoneFactory->delete_zone(intval($deleteCheckResultObj->PublisherAdZoneID))) > -1):

            		    			$PublisherAdZoneVideoFactory->delete_zone(intval($deleteCheckResultObj->PublisherAdZoneID));
            		    		
            		    			// Delete success! Return to publisher.
            		    			$success = true;
            		    			            		    		
            		    		else:
            		    		
            		    			// Something blew up.
            		    			$error_message = "Unable to delete the entry. Please contact customer service.";
            		    		endif;
            		    	
            		    	else:
            		    	
            		    		// User is either not the owner of the entry, or is not an admin.
            		    		$error_message = "You do not have permission to delete this entry.";
            		    	endif;
            		    
            		    else:
          		    
            		    	// Cancel.
            		    endif;
            
            		
            		else:
            		
            			//OK Display edit.
            		endif;
            
            	
            	//else:
            	
            		//$error_message = "An invalid Ad Zone ID was provided.";
            	//endif;
            
            
            else:
            
            	$error_message = "An invalid Ad Zone ID was provided.";
            endif;
        endif;
        
         $data = array(
	        'success' => $success,
	        'data' => array('error_msg' => $error_message)
   		 );
   		 
         $this->setJsonHeader();
         return $this->getResponse()->setContent(json_encode($data));
        
    }
    
    /**
     * Toggle the approval given the supplied flag to toggle.
     *
     * @param integer $flag 0 = Pending | 1 = Approved
     * @return boolean TRUE if successful, FALSE if failure.
     */
    private function adApprovalToggle($flag)
    {
        $DomainID = intval($this->params()->fromRoute('param1', 0));
        $PublisherAdZoneID = intval($this->params()->fromRoute('id',0));
        
        if (($this->is_super_admin || $this->is_domain_admin) && $DomainID > 0 && $PublisherAdZoneID > 0 && ($flag === 0 || $flag === 1 || $flag === 2)):
        
            $DomainObj = $this->get_domain_data($DomainID, $this->PublisherInfoID);
            
            if ($DomainObj === null):
            
            	$error_message = "An invalid publishing web domain was specified for the specified user.";
            
            else: 
            
	            if ($this->is_domain_admin):
		            if (!\util\AuthHelper::domain_user_authorized_publisher($this->auth->getUserID(), $DomainObj->DomainOwnerID)):
		         	   die("Not Authorized");
		            endif;
	            endif;
            
                $PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
                $AdObject = new \model\PublisherAdZone();
                $parameters = array("PublisherWebsiteID" => $DomainObj->PublisherWebsiteID, "PublisherAdZoneID" => $PublisherAdZoneID);
                $AdObject = $PublisherAdZoneFactory->get_row_object($parameters);
                
                if (intval($AdObject->PublisherAdZoneID) == $PublisherAdZoneID):
                	
                	$AdObject->AutoApprove = 0;

                    $AdObject->AdStatus = intval($flag);
                    if ($PublisherAdZoneFactory->save_ads($AdObject)):
                    
	                    if (($flag == 1 || $flag == 2) && $this->config_handle['mail']['subscribe']['user_zones']):
	                    
		                    $PublisherInfoFactory = \_factory\PublisherInfo::get_instance();
		                    $params = array();
		                    $params["PublisherInfoID"] = $DomainObj->DomainOwnerID;
		                    $PublisherInfo = $PublisherInfoFactory->get_row($params);
		                    
		                    if ($PublisherInfo !== null):
			                    // approval, send out email
			                    
			                    $site_url 		= $this->config_handle['delivery']['site_url'];
			                    $exchange_name 	= $this->config_handle['delivery']['exchange_name'];
			                    
		                    	if ($flag == 1):
			                   		$message = 'Your ' . $exchange_name . ' Publisher Ad Zone for : ' . $DomainObj->WebDomain . ' : ' . $AdObject->AdName . ' was approved.<br /><br />Please login <a href="' . $site_url . '/auth/login">here</a> with your email and password';
			                    	$subject = "Your " . $exchange_name . " Publisher Ad Zone for : " . $DomainObj->WebDomain . " was approved";
			                    else:
				                    $message = 'Your ' . $exchange_name . ' Publisher Ad Zone for : ' . $DomainObj->WebDomain . ' : ' . $AdObject->AdName . ' was rejected.<br /><br />Please login <a href="' . $site_url . '/auth/login">here</a> with your email and password';
				                    $subject = "Your " . $exchange_name . " Publisher Ad Zone for : " . $DomainObj->WebDomain . " was rejected";
			                    endif;
			                    $transport = $this->getServiceLocator()->get('mail.transport');
			                    
			                    $text = new Mime\Part($message);
			                    $text->type = Mime\Mime::TYPE_HTML;
			                    $text->charset = 'utf-8';
			                    
			                    $mimeMessage = new Mime\Message();
			                    $mimeMessage->setParts(array($text));
			                    $zf_message = new Message();
			                    $zf_message->addTo($PublisherInfo->Email)
			                    ->addFrom($this->config_handle['mail']['reply-to']['email'], $this->config_handle['mail']['reply-to']['name'])
			                    ->setSubject($subject)
			                    ->setBody($mimeMessage);
			                    $transport->send($zf_message);
			             	endif;
	                   	endif;
	                   	
                        return TRUE;
                    
                  	endif;
                endif;
            endif;
        endif;
        
        return FALSE;
        
    }
    
    /**
     * Approve an Ad space.
     * @return Ambigous <\Zend\Http\Response, \Zend\Stdlib\ResponseInterface>
     */
    public function approveAction()
    {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
        $DomainID = intval($this->params()->fromRoute('param1', 0));
        $this->adApprovalToggle(1);
        return $this->redirect()->toRoute('publisher/zone',array("param1" => $DomainID));
    }
    
    /**
     * Reject an Ad space.
     * @return Ambigous <\Zend\Http\Response, \Zend\Stdlib\ResponseInterface>
     */
    public function rejectAction()
    {
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
        $DomainID = intval($this->params()->fromRoute('param1', 0));
        $this->adApprovalToggle(2);
        return $this->redirect()->toRoute('publisher/zone',array("param1" => $DomainID));
        
    }
    
    /**
     * Header Bidding Ad Tag generation for zone.
     *
     * @return Header Bidding Ad Tag
     */
    public function generateHbTagAction()
    {
    
    	$initialized = $this->initialize();
    	if ($initialized !== true) return $initialized;
    	$request = $this->getRequest();
    	 
    	if ($request->isPost()):
	    	 
	    	$PublisherAdZoneID = $this->getRequest()->getPost('ad_id');
	    	$PublisherWebsiteID = intval($this->params()->fromRoute('param1', 0));
	    
	    	$PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
	    	$HeaderBiddingPageFactory = \_factory\HeaderBiddingPage::get_instance();
	    	$HeaderBiddingAdUnitFactory = \_factory\HeaderBiddingAdUnit::get_instance();
	    	
	    	$params = array();
	    	$params["PublisherAdZoneID"] 	= $PublisherAdZoneID;
	    	$PublisherAdZone		 		= $PublisherAdZoneFactory->get_row($params);
	    	
	    	if ($PublisherAdZone == null):
		    	
		    	$data = array(
		    			'result' => true,
		    			'data' => array(
		    					'header_tag' => htmlentities('There was a database error, please contact the administrator'),
		    					'code_tag' => htmlentities('There was a database error, please contact the administrator')
		    			)
		    	);
		    	
		    	$this->setJsonHeader();
		    	return $this->getResponse()->setContent(json_encode($data));
	    	
	    	endif;
	    	
	    	$params = array();
	    	$params["HeaderBiddingPageID"] 	= $PublisherAdZone->HeaderBiddingPageID;
	    	$HeaderBiddingPage		 		= $HeaderBiddingPageFactory->get_row($params);
	    	
	    	$params = array();
	    	$params["PublisherAdZoneID"] 	= $PublisherAdZoneID;
	    	$HeaderBiddingAdUnit		 	= $HeaderBiddingAdUnitFactory->get_row($params);
	    	
	    	if ($HeaderBiddingPage == null
	    		|| $HeaderBiddingAdUnit == null):
	    		
	    		$data = array(
	    				'result' => true,
	    				'data' => array(
	    						'header_tag' => htmlentities('There was a database error, please contact the administrator'),
	    						'code_tag' => htmlentities('There was a database error, please contact the administrator')
	    				)
	    		);
	    		 
	    		$this->setJsonHeader();
	    		return $this->getResponse()->setContent(json_encode($data));
	    		
	    	endif;
	    	
	    	$div_id 						= $HeaderBiddingAdUnit->DivID;
	
	    	$delivery_site_url 				= $this->config_handle['delivery']['site_url'];
	    	
	    	$code_tag						= "<div id='" . $div_id . "' style='display: inline; margin: 0px; padding: 0px; height:" . $PublisherAdZone->Height . "px; width:" . $PublisherAdZone->Width . "px;'></div>";
	          
	    	$header_tag						= "<script type='text/javascript' src='" . $delivery_site_url . "/headerbid/" . $PublisherAdZone->AdOwnerID . "/" . $HeaderBiddingPage->JSHeaderFileUnqName . "'></script>";
	          
	    	$data = array(
	    			'result' => true,
	    			'data' => array(
	    							'header_tag' => htmlentities($header_tag),
	    							'code_tag' => htmlentities($code_tag)
	    							)
	    	);
	    
	    	$this->setJsonHeader();
	    	return $this->getResponse()->setContent(json_encode($data));
	    
    	endif;
    }
    
    /**
     * VAST Ad Tag generation for zone.
     *
     * @return VAST Ad Tag
     */
    public function generateVastTagAction()
    {
    
    	$initialized = $this->initialize();
    	if ($initialized !== true) return $initialized;
	    	$request = $this->getRequest();
	    
	    if ($request->isPost()):
	    	 
	    	$PublisherAdZoneID = $this->getRequest()->getPost('ad_id');
	    	$PublisherWebsiteID = intval($this->params()->fromRoute('param1', 0));
	    	
	    	$PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
	    	
	    	$params = array();
	    	$params["PublisherAdZoneID"] = $PublisherAdZoneID;
	    	$AdObject = $PublisherAdZoneFactory->get_row_object($params);

	    	$delivery_adtag = $this->config_handle['delivery']['url'];
	    	
	    	$effective_tag = $delivery_adtag . "?video=vast&pzoneid=" . $PublisherAdZoneID;
	    	
	    	$data = array(
	    			'result' => true,
	    			'data' => array('tag' => htmlentities($effective_tag))
	    	);
	    	
	    	$this->setJsonHeader();
	    	return $this->getResponse()->setContent(json_encode($data));
	    	
	    endif;
    }
    
    /**
     * Ad Tag generation for zone.
     *
     * @return Ad Tag
     */
     public function generateTagAction()
     {
     	
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
        $request = $this->getRequest();
        
        if ($request->isPost()):
        
          $PublisherAdZoneID = $this->getRequest()->getPost('ad_id');
          $PublisherWebsiteID = intval($this->params()->fromRoute('param1', 0));
          
          $PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
          $PublisherWebsiteFactory = \_factory\PublisherWebsite::get_instance();
          
          $params = array();
		  $params["PublisherAdZoneID"] = $PublisherAdZoneID;
          $AdObject = $PublisherAdZoneFactory->get_row_object($params);
          
          $params = array();
		  $params["PublisherWebsiteID"] = $PublisherWebsiteID;
          $PublishObject = $PublisherWebsiteFactory->get_row_object($params);
          
          $width = 0;
          $height = 0;
          $domain = $PublishObject->WebDomain;
          if($AdObject->AdTemplateID != NULL && $AdObject->AdTemplateID != 0):
          
          	$AdTemplatesFactory = \_factory\AdTemplates::get_instance();
	        $params = array();
	        $params['AdTemplateID'] = $AdObject->AdTemplateID;
	        $AdTemplatesObject = $AdTemplatesFactory->get_row_object($params);
          	$height = $AdTemplatesObject->Height;
          	$width = $AdTemplatesObject->Width;
          
          else:
          	
          	$height = $AdObject->Height;
            $width = $AdObject->Width;
          
          endif;  
          
          $delivery_adtag = $this->config_handle['delivery']['adtag'];
          
          $cache_buster = time();
          	
          $effective_tag = "<script type='text/javascript' src='" . $delivery_adtag . "?pzoneid=" . $PublisherAdZoneID . "&height=" . $height . "&width=" . $width . "&tld=" . $domain . "&cb=" . $cache_buster . "'></script>";
          
          $data = array(
	        'result' => true,
	        'data' => array('tag' => htmlentities($effective_tag))
   		 );
          
          $this->setJsonHeader();
          return $this->getResponse()->setContent(json_encode($data));

        endif;
     }
     
     /**
      *
      * @return JSON encoded data for AJAX call
      */
     public function editlinkedbannerAction() {
     	
     	$id 		= intval($this->params()->fromRoute('id', 0));
     	$height 	= $this->getRequest()->getQuery('height');
     	$width 		= $this->getRequest()->getQuery('width');
     
		if ($height == null || $width == null):
			$data = array(
					'success' => false,
					'linked_ad_zones' => "", 
					'complete_zone_list' => array()
			);
			$this->setJsonHeader();
			return $this->getResponse()->setContent(json_encode($data));
		endif;
	
		$initialized = $this->initialize();
		if ($initialized !== true) return $initialized;
		
		if (!$this->is_super_admin):
			$data = array(
					'success' => false,
					'linked_ad_zones' => "", 
					'complete_zone_list' => array()
			);
			$this->setJsonHeader();
			return $this->getResponse()->setContent(json_encode($data));
		endif;

     	// verify
     	$linked_ad_banners = array();
     
     	$PublisherAdZoneFactory = \_factory\PublisherAdZone::get_instance();
     	
     	if ($id):
     	
	     	$params = array("PublisherAdZoneID" => $id);
	     	$PublisherAdZone = $PublisherAdZoneFactory->get_row_object($params);
	     	
	     	if ($PublisherAdZone == null || $PublisherAdZone->AdOwnerID != $this->PublisherInfoID):
	     		$error_message = "An invalid Ad Zone ID was provided.";
		     	$data = array(
		     			'success' => false,
		     			'error'	=> $error_message,
		     			'linked_ad_zones' => "",
		     			'complete_zone_list' => array()
		     	);
		     	$this->setJsonHeader();
		     	return $this->getResponse()->setContent(json_encode($data));
	     	endif;
	     	
		endif;

     	$params = array();
     	$params["Height"] 	= $height;
     	$params["Width"] 	= $width;
     	$params["Active"] 	= 1;
     	// $params["UserID"] 	= $this->EffectiveID;
     
     	$InsertionOrderLineItemFactory = \_factory\InsertionOrderLineItem::get_instance();
     	$InsertionOrderLineItemList = $InsertionOrderLineItemFactory->get($params);
     	if ($InsertionOrderLineItemList === null):
     		$InsertionOrderLineItemList = array();
     	endif;
     
     	$complete_banner_list = array();
     
     	foreach ($InsertionOrderLineItemList as $InsertionOrderLineItem):
     
	     	$complete_banner_list[] = array(
	     			"banner_id"	=>	$InsertionOrderLineItem->InsertionOrderLineItemID,
	     			"ad_name"	=>	$InsertionOrderLineItem->Name
	     	);
	     
     	endforeach;
     
     	$linked_banner_list = array();
     
     	foreach ($linked_ad_banners as $linked_ad_banner):
     
     		$linked_banner_list[] = $linked_ad_banner->InsertionOrderLineItemID;
     		
     	endforeach;
     
     	$data = array(
     			'success' => count($InsertionOrderLineItemList) > 0,
     			'linked_ad_banners' => implode(',', $linked_banner_list),
     			'complete_banner_list' => $complete_banner_list
     	);
     	$this->setJsonHeader();
     	return $this->getResponse()->setContent(json_encode($data));
     
     }
     
}