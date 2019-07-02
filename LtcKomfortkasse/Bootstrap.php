<?php
class Shopware_Plugins_Backend_LtcKomfortkasse_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    // TODO check for allow_url_fopen

    public function getVersion()
    {
        return '1.2.5';

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
        return array ('version' => $this->getVersion(),'copyright' => 'Copyright (c) Komfortkasse','label' => $this->getLabel(),'supplier' => 'Komfortkasse','description' => $this->getDescription(),'support' => 'https://komfortkasse.eu/support','link' => 'https://komfortkasse.eu'
        );

    }


    public function getCapabilities()
    {
        return array ('install' => true,
                'update' => true,
                'enable' => true
        );

    }


    public function install()
    {
        Shopware()->Loader()->registerNamespace('Shopware_Components_Komfortkasse', dirname(__FILE__) . '/Components/Komfortkasse/');

        $this->subscribeEvents();
        $this->createForm();

        return true;
    }

    protected function subscribeEvents() {
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch_Frontend_Checkout', 'onPostDispatchCheckout');
        $this->subscribeEvent('Shopware_Components_Document::assignValues::after', 'afterCreatingDocument');
        $this->subscribeEvent('Shopware\Models\Order\Order::postUpdate', 'updateOrder', 99);
    }

    protected function createForm() {
        $form = $this->Form();
        $parent = $this->Forms()->findOneBy(array ('name' => 'Frontend'
        ));
        $form->setParent($parent);
        $form->setElement('checkbox', 'active',
                array ('label' => 'Plugin aktivieren','value' => true,'scope' => 1 /*Shopware/Models/Config/Element::SCOPE_SHOP*/)
        );
        if (method_exists('Shopware\Models\Attribute\OrderDetail', 'setViisonCanceledQuantity')) {
            $form->setElement('checkbox', 'cancelDetail',
                    array ('label' => 'Bestellpositionen stornieren (Pickware/Shopware ERP)','value' => false,'scope' => 1 /*Shopware/Models/Config/Element::SCOPE_SHOP*/)
            );
            $this->addFormTranslations(
                    array ('en_GB' =>
                            array ('plugin_form' => array ('label' => 'activate plugin'),
                                    'cancelDetail' => array ('label' => 'Cancel order details (Pickware/Shopware ERP)')
                            )
                    )
            );
        } else {
            $this->addFormTranslations(
                    array ('en_GB' =>
                            array ('plugin_form' => array ('label' => 'activate plugin'))
                    )
            );
        }
    }

    public function update($existingVersion)
    {
        $this->subscribeEvents();
        $this->createForm();

        return true;
    }


    public function updateOrder(Enlight_Event_EventArgs $arguments)
    {
        $config = $this->Config();
        if (empty($config->active)) {
            return;
        }

        $order = $arguments->get('entity');
        $historyList = $order->getHistory();

        // if order is new: notify Komfortkasse about order

        if ($order->getNumber()) {
            if ($historyList->count() == 0 || ($historyList->count() == 1 && $historyList->last()->getPreviousPaymentStatus()->getId() == 0)) {
                $site_url = Shopware()->System()->sCONFIG ["sBASEPATH"];
                $query = http_build_query(array ('id' => $order->getId(),'url' => $site_url
                ));
                $contextData = array ('method' => 'POST','timeout' => 2,'header' => "Connection: close\r\n" . 'Content-Length: ' . strlen($query) . "\r\n",'content' => $query
                );
                $context = stream_context_create(array ('http' => $contextData
                ));
                $result = @file_get_contents('http://api.komfortkasse.eu/api/shop/neworder.jsf', false, $context);
                return;
            }
        }

        // if order has been cancelled: cancel details in pickware/shopware erp

        if (empty($config->cancelDetail)) {
            return;
        }
        if (!method_exists('Shopware\Models\Attribute\OrderDetail', 'setViisonCanceledQuantity'))
            return;

        if (strpos($order->getTransactionID(), 'Komfortkasse') === false)
            return;

        $history = $historyList->last();
        if ($history && $history->getPreviousOrderStatus()->getId() != 4 && $history->getOrderStatus()->getId() == 4) {
            $em = Shopware()->Container()->get('models');
            foreach ($order->getDetails()->toArray() as $detail) {
                $attr = $detail->getAttribute();
                if ($attr) {
                    $qty = $detail->getQuantity();
                    $detail->setQuantity(0);
                    $detail->setShipped(0);
                    $attr->setViisonCanceledQuantity($attr->getViisonCanceledQuantity() + $qty);
                    // ab Shopware 5.2 existiert die Methode setViisonPickedQuantity nicht mehr
                    if (method_exists('Shopware\Models\Attribute\OrderDetail', 'setViisonPickedQuantity'))
                        $attr->setViisonPickedQuantity(0);
                    $em->persist($detail);
                }
            }
            $em->flush();
        }

    }


    public function onPostDispatchCheckout(Enlight_Event_EventArgs $arguments)
    {
        $config = $this->Config();
        if (empty($config->active)) {
            return;
        }
        $subject = $arguments->getSubject();
        $request = $subject->Request();
        $response = $subject->Response();
        $action = $request->getActionName();

        if ($action === 'finish') {
            $site_url = Shopware()->System()->sCONFIG ["sBASEPATH"];
            $ordernum = $_SESSION ['Shopware'] ['sOrderVariables']->sOrderNumber;
            if ($ordernum) {
                $query = http_build_query(array ('number' => $ordernum,'url' => $site_url));
            } else {
                $temp_id = $_SESSION ['Shopware'] ['sessionId'];
                $id = Shopware()->Db()->fetchOne("SELECT id FROM s_order WHERE temporaryID = ?", array ($temp_id));
                $query = http_build_query(array ('id' => $id,'url' => $site_url));
            }

            $contextData = array ('method' => 'POST','timeout' => 2,'header' => "Connection: close\r\n" . 'Content-Length: ' . strlen($query) . "\r\n",'content' => $query
            );

            $context = stream_context_create(array ('http' => $contextData
            ));

            $result = @file_get_contents('http://api.komfortkasse.eu/api/shop/neworder.jsf', false, $context);
        }

    }


    public function afterCreatingDocument(Enlight_Hook_HookArgs $arguments)
    {
        $config = $this->Config();
        if (empty($config->active)) {
            return;
        }
        $document = $arguments->getSubject();

        $rid = Shopware()->Db()->fetchOne("SELECT docID FROM s_order_documents WHERE orderID = " . $document->_order->shipping ['orderID'] . " AND type = 1");

        if ($rid != '') {
            $site_url = Shopware()->System()->sCONFIG ["sBASEPATH"];
            $query = http_build_query(array ('id' => $document->_order->shipping ['orderID'],'url' => $site_url,'invoice_number' => $rid
            ));

            $contextData = array ('method' => 'POST','timeout' => 2,'header' => "Connection: close\r\n" . 'Content-Length: ' . strlen($query) . "\r\n",'content' => $query
            );

            $context = stream_context_create(array ('http' => $contextData
            ));

            $result = @file_get_contents('http://api.komfortkasse.eu/api/shop/invoice.jsf', false, $context);
        }

    }
}
