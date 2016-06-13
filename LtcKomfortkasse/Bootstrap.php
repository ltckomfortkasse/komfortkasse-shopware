<?php

class Shopware_Plugins_Backend_Komfortkasse_Bootstrap extends Shopware_Components_Plugin_Bootstrap 
{
	
	public function getVersion() 
	{
		return '1.1.0';
	}
	
	public function getLabel() 
	{
		return 'Komfortkasse - Zahlung per Banküberweisung';
	}
	
	public function getDescription() 
	{
		return 'Vollautomatische Verarbeitung von Zahlungen per Banküberweisung (Vorkasse, Rechnung, Nachnahme)';
	}
	
	
	public function getInfo() 
	{
		return array (
				'version' => $this->getVersion (),
				'copyright' => 'Copyright (c) Komfortkasse',
				'label' => $this->getLabel (),
				'supplier' => 'Komfortkasse',
				'description' => $this->getDescription (),
				'support' => 'https://komfortkasse.eu/support',
				'link' => 'https://komfortkasse.eu' 
		);
	}
	
	public function getCapabilities() 
	{
		return array (
				'install' => true,
				// 'update' => true,
				'enable' => true 
		);
	}
	
	public function install() 
	{
		Shopware ()->Loader ()->registerNamespace ( 'Shopware_Components_Komfortkasse', dirname ( __FILE__ ) . '/Components/Komfortkasse/' );
		$this->subscribeEvent ( 'Enlight_Controller_Action_PostDispatch_Frontend_Checkout', 'onPostDispatchCheckout' );
		$this->subscribeEvent ( 'Shopware_Components_Document::assignValues::after', 'afterCreatingDocument' );
		
		$form = $this->Form ();
		$parent = $this->Forms ()->findOneBy ( array (
				'name' => 'Frontend' 
		) );
		$form->setParent ( $parent );
		$form->setElement ( 'checkbox', 'active', array (
				'label' => 'Plugin aktivieren',
				'value' => true,
				'scope' => Shopware\Models\Config\Element::SCOPE_SHOP 
		) );
		
		$this->addFormTranslations ( array (
				'en_GB' => array (
						'plugin_form' => array (
								'label' => 'activate plugin' 
						) 
				) 
		) );
		
		return true;
	}
	
	public function onPostDispatchCheckout(Enlight_Event_EventArgs $arguments) 
	{
		$subject = $arguments->getSubject ();
		$request = $subject->Request ();
		$response = $subject->Response ();
		$action = $request->getActionName ();
		
		$temp_id = $_SESSION ['Shopware'] ['sessionId'];
		$id = Shopware ()->Db ()->fetchOne ( "SELECT id FROM s_order WHERE temporaryID = ?", array (
				$temp_id 
		) );
		$site_url = Shopware ()->System ()->sCONFIG ["sBASEPATH"];
		
		if ($action === 'finish') {
			
			$query = http_build_query ( array (
					'id' => $id,
					'url' => $site_url 
			) );
			
			$contextData = array (
					'method' => 'POST',
					'timeout' => 2,
					'header' => "Connection: close\r\n" . 'Content-Length: ' . strlen ( $query ) . "\r\n",
					'content' => $query 
			);
			
			$context = stream_context_create ( array (
					'http' => $contextData 
			) );
			
			$result = @file_get_contents ( 'http://api.komfortkasse.eu/api/shop/neworder.jsf', false, $context );
		}
	}
	
	public function afterCreatingDocument(Enlight_Hook_HookArgs $arguments) 
	{
		$document = $arguments->getSubject ();
		
		$rid = Shopware ()->Db ()->fetchOne ( "SELECT docID FROM s_order_documents WHERE orderID = ?", $document->_order->shipping ['orderID'] . " AND type = ?", 1 );
		
		if ($rid != '') {
			$site_url = Shopware ()->System ()->sCONFIG ["sBASEPATH"];
			$query = http_build_query ( array (
					'id' => $document->_order->shipping ['orderID'],
					'url' => $site_url,
					'invoice_number' => $rid 
			) );
			
			$contextData = array (
					'method' => 'POST',
					'timeout' => 2,
					'header' => "Connection: close\r\n" . 'Content-Length: ' . strlen ( $query ) . "\r\n",
					'content' => $query 
			);
			
			$context = stream_context_create ( array (
					'http' => $contextData 
			) );
			
			$result = @file_get_contents ( 'http://api.komfortkasse.eu/api/shop/invoice.jsf', false, $context );
		}
	}
}
	
}
