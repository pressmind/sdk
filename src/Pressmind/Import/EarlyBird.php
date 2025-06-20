<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup;
use Pressmind\Registry;
use Pressmind\REST\Client;

class EarlyBird extends AbstractImport implements ImportInterface
{

    /**
     * @return mixed|void
     */
    public function import()
    {
        $client = new Client();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        try {
            $response = $client->sendRequest('EarlyBird', 'search');
            $this->_checkApiResponse($response);
            $earlybird_group_ids = [];
            $earlybird_group_item_ids = [];
            if (!empty($response->result) && is_array($response->result)) {
                foreach ($response->result as $result) {
                    try {
                        if(empty($result->scales)){
                            continue;
                        }
                        $EarlyBirdGroup = new EarlyBirdDiscountGroup();
                        $EarlyBirdGroup->id = $result->id;
                        $EarlyBirdGroup->name = empty($result->name) ? $result->import_code : $result->name;
                        $EarlyBirdGroup->create();
                        foreach($result->scales as $scale){
                            if($scale->travel_date_to <  $today){
                                continue;
                            }
                            if($scale->booking_date_to <  $today){
                                continue;
                            }
                            $Item = new EarlyBirdDiscountGroup\Item();
                            $Item->id = $scale->id;
                            $Item->id_early_bird_discount_group = $EarlyBirdGroup->getId();
                            $Item->travel_date_from = $scale->travel_date_from;
                            $Item->travel_date_to = $scale->travel_date_to;
                            $Item->booking_date_from = $scale->booking_date_from;
                            $Item->booking_date_to = $scale->booking_date_to;
                            $Item->discount_value = $scale->discount_value;
                            $Item->type = $scale->type;
                            $Item->round = $scale->round;
                            $Item->early_payer = $scale->early_payer;
                            $Item->create();
                        }
                        if(empty($earlybird_group_item_ids)){
                            $EarlyBirdGroup->delete();
                        }else{
                            $earlybird_group_ids[] = $EarlyBirdGroup->getId();
                        }
                    } catch (Exception $e) {
                        $this->_errors[] = $e->getMessage();
                    }
                }
                $this->remove_orphans($earlybird_group_ids);
            }
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
    }

    /**
     * @param $earlybird_group_ids
     * @return void
     * @throws Exception
     */
    public function remove_orphans($earlybird_group_ids)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $EarlyBirdGroup = new EarlyBirdDiscountGroup();
        $Date = new Date();
        $sql = 'DELETE FROM '.$EarlyBirdGroup->getDbTableName().'
                WHERE NOT EXISTS (
                SELECT 1 FROM '.$Date->getDbTableName().' WHERE '.$Date->getDbTableName().'.id_early_bird_discount_group = '.$EarlyBirdGroup->getDbTableName().'.id)';
        if(!empty($earlybird_group_ids)) {
            $sql .= ' and ' . $EarlyBirdGroup->getDbTableName() . '.id not in("'.implode('","', $earlybird_group_ids).'")';
        }
        $db->execute($sql);
        $EarlyBirdGroupItem = new EarlyBirdDiscountGroup\Item();
        $sql = 'DELETE FROM '.$EarlyBirdGroupItem->getDbTableName().'
                WHERE NOT EXISTS (
                SELECT 1 FROM '.$EarlyBirdGroup->getDbTableName().' WHERE '.$EarlyBirdGroupItem->getDbTableName().'.id_early_bird_discount_group = '.$EarlyBirdGroup->getDbTableName().'.id)';
        $db->execute($sql);
    }
}
