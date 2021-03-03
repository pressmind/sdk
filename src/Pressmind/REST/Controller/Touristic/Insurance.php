<?php


namespace Pressmind\REST\Controller\Touristic;


use Exception;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\REST\Controller\AbstractController;

class Insurance extends AbstractController
{
    protected $orm_class_name = '\\Pressmind\\ORM\\Object\\Touristic\\Insurance';

    /**
     * @param $params
     * @return array
     * @throws Exception
     */
    public function calculatePrices($params)
    {
        if(!isset($params['id_media_object'])) {
            throw new Exception('required parameter id_media_object is missing');
        }
        if(!isset($params['price_person'])) {
            throw new Exception('required parameter price_person is missing');
        }
        if(!isset($params['duration_nights'])) {
            throw new Exception('required parameter duration_nights is missing');
        }
        if(!isset($params['date_start'])) {
            throw new Exception('required parameter date_start is missing');
        }
        if(!isset($params['date_end'])) {
            throw new Exception('required parameter date_end is missing');
        }
        $date_start = \DateTime::createFromFormat('Y-m-d', $params['date_start']);
        if(!$date_start) {
            throw new Exception('parameter date_start is not in the format YYYY-MM-DD');
        }
        $date_start->setTime(0, 0, 0);
        $date_end = \DateTime::createFromFormat('Y-m-d', $params['date_end']);
        if(!$date_end) {
            throw new Exception('parameter date_end is not in the format YYYY-MM-DD');
        }
        $person_age = isset($params['age_person']) ? $params['age_person'] : 18;
        $total_number_of_persons =  isset($params['total_number_of_participants']) ? $params['total_number_of_participants'] : 0;
        $date_end->setTime(23, 59, 59);
        $mediaObject = new MediaObject(intval($params['id_media_object']), true);
        $response = [];
        foreach ($mediaObject->insurance_group->insurances as $insurance) {
            if($insurance->active == true && $available = $insurance->isAvailableForTravelDateAndPriceAndPersonAge($date_start, $date_end, floatval($params['price_person']), intval($params['duration_nights']), $person_age, $total_number_of_persons)) {
                $response[] = $available;
            }
        }
        return $response;
    }
}
