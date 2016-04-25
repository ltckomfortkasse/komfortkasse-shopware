<?php

class Shopware_Plugins_Backend_Komfortkasse_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    public function getVersion()
    {
        return '1.0.0';
    }

    public function getLabel()
    {
        return 'Komfortkasse';
    }

    public function install()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout','onPostDispatchCheckout'
        );

        return true;
    }

    
    public function onPostDispatchCheckout(Enlight_Event_EventArgs $arguments)
    {
        $subject = $arguments->getSubject();
        $request  = $subject->Request();
        $response = $subject->Response();
        $action = $request->getActionName();

        $temp_id = $_SESSION['Shopware']['sessionId'];
        $id = Shopware()->Db()->fetchOne("SELECT id FROM s_order WHERE temporaryID = ?", array($temp_id));
        $site_url = 'http://'.Shopware()->System()->sCONFIG["sBASEPATH"];
        
        if ( $action === 'finish'){

			$query = http_build_query(array ('id' => $id,'url' => $site_url
			));
			
			$contextData = array ('method' => 'POST','timeout' => 2,'header' => "Connection: close\r\n" . 'Content-Length: ' . strlen($query) . "\r\n",'content' => $query
			);
			
			$context = stream_context_create(array ('http' => $contextData
			));
			
			$result = @file_get_contents('http://api.komfortkasse.eu/api/shop/neworder.jsf', false, $context);
        }

    }

}
