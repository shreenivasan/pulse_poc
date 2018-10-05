<?php

class process_request{
 
    var $_db = null;
    
    public function __construct() {
        $this->_db = new Dbconfig();
    }
    
    public function is_first_request(){
        $sql = "select count(*) as cnt , id  from tbl_request_response_entry group by id";
        return $res = $this->_db->query($sql);
    }
    
    public function process_first_request($id , $thresholdValues) {
        
        //$thresholdValue = $this->get_current_threshold_value(date("H"), $thresholdValues);
        $thresholdValue = $thresholdValues['active_till']; // Hard coded as per suggestion vijay
        
        $tmp_storewise_till_data = $this->get_storewise_till_data($id);
        $storewise_till_data = [];
        
        $data_to_push = [];
        echo '<pre>';
        foreach( $tmp_storewise_till_data as $k => $v ){
            $tmp_till = explode(",", $v['till_number']);
            $tmp_invoice_cnt = explode(",", $v['invoice_count']);
            if( count($tmp_till) > 1 ){
                $storewise_till_data[$v['store_code']]['till_numbers'] = $tmp_till;
                $storewise_till_data[$v['store_code']]['invoice_count'] = $tmp_invoice_cnt;
                $storewise_till_data[$v['store_code']]['max_invoice_count'] = max($storewise_till_data[$v['store_code']]['invoice_count']);
            
                $till_cnt = count($tmp_till); 
                
                for($i=0 ; $i < $till_cnt; $i++){
                    $utilization_value = round((( $tmp_invoice_cnt[$i] * 100 ) / $storewise_till_data[$v['store_code']]['max_invoice_count']));
                    if( $utilization_value <= $thresholdValue ) {
                        $storewise_till_data[$v['store_code']]['utilization_percentage'][] = $utilization_value;
                    }
                }                        
            }
            
            unset($storewise_till_data[$v['store_code']]['till_numbers']);
            unset($storewise_till_data[$v['store_code']]['invoice_count']);
            unset($storewise_till_data[$v['store_code']]['max_invoice_count']);
            
            if( empty($storewise_till_data[$v['store_code']]['utilization_percentage']) ){
                unset($storewise_till_data[$v['store_code']]);
            }
            
        }  
        
        print_r($storewise_till_data);
        return $storewise_till_data;
    }
    
    private function get_storewise_till_data($id){
        $params[] = $id; 
        $sql = "select s.store_code , GROUP_CONCAT(till_number) as till_number , GROUP_CONCAT(invoice_count) as invoice_count
                from  tbl_store_till_entry as tste 
                LEFT JOIN tbl_request_response_entry as rre on tste.req_response_entry_id = rre.id
                INNER JOIN tbl_stores as s on tste.store_id = s.id
                WHERE rre.id =  ?
                group by rre.id,s.store_code";
        return $res = $this->_db->query($sql , $params);        
    }
    
    public function get_current_threshold_value($hour , $thresholdValues){
        $return_flag = '0';
        
        if( $hour >=0 && $hour <= 12 ){
            $return_flag = $thresholdValues['10:00:00-12:59:59'];
        }elseif($hour >=13 && $hour <= 15){
            $return_flag = $thresholdValues['13:00:00-15:59:59'];
        }elseif($hour == 16){
            $return_flag = $thresholdValues['16:00:00-16:59:59'];
        }
        elseif($hour == 17){
            $return_flag = $thresholdValues['17:00:00-17:59:59'];
        }
        elseif($hour == 18){
            $return_flag = $thresholdValues['18:00:00-18:59:59'];
        }
        elseif($hour >=19 && $hour <= 22){
            $return_flag = $thresholdValues['19:00:00-22:00:00'];
        }
       
        return $return_flag;
    }
    
    private function get_last_two_trans_ids(){
        $sql = "select id  from tbl_request_response_entry ORDER BY id desc limit 2 ";
        return $res = $this->_db->query($sql);
    }

//    private function get_two_subsequent_trans_data($ids){
//        $params[] = $ids;
//        $sql = "select s.store_code , GROUP_CONCAT(till_number) as till_number , GROUP_CONCAT(invoice_count) as invoice_count
//                from  tbl_store_till_entry as tste 
//                LEFT JOIN tbl_request_response_entry as rre on tste.req_response_entry_id = rre.id
//                INNER JOIN tbl_stores as s on tste.store_id = s.id
//                WHERE rre.id in (?)
//                group by rre.id,s.store_code";
//        return $res = $this->_db->query($sql , $params);
//    }

    public function process_all_request( $thresholdValues ) {
            
        $thresholdValue = $thresholdValues['active_till'];
        
        $last_two_trans_ids = $this->get_last_two_trans_ids();
        $last_two_trans_ids = array_column($last_two_trans_ids, "id");
        
        if(!empty($last_two_trans_ids)){           
           
            $trans1_data = $this->get_storewise_till_data($last_two_trans_ids[0]);
            $trans2_data = $this->get_storewise_till_data($last_two_trans_ids[1]);
            $trans1 = [];
            $trans2 = [];
            
            foreach($trans1_data as $k => $v){
                $till_number = explode("," , $v['till_number']);
                $invoice_count = explode("," , $v['invoice_count']);
                $combined = array_combine($till_number, $invoice_count);
                $trans1[$v['store_code']]['till_data'] = $combined;
            }
            
            foreach($trans2_data as $k => $v){
                $till_number = explode("," , $v['till_number']);
                $invoice_count = explode("," , $v['invoice_count']);
                $combined = array_combine($till_number, $invoice_count);
                $trans2[$v['store_code']]['till_data'] = $combined;
            }
            
            $final_till_data = [];
            if ( count($trans1) == count($trans2)) {
                
                foreach( $trans1 as $store_code => $till_data ){
                    
                    foreach ($trans2[$store_code]['till_data'] as $k => $v){
                        $till_invoice_cnt_1 = !empty($trans1[$store_code]['till_data'][$k]) ? $trans1[$store_code]['till_data'][$k] : 0;
                        $till_invoice_cnt_2 = !empty($v) ? $v : 0;
                        $final_till_data[$store_code][$k] = $till_invoice_cnt_1 - $till_invoice_cnt_2;
                    }
                    
                    $max_invoice_cnt =  max($final_till_data[$store_code]);

                    if( count($final_till_data[$store_code]) <= 1 ){
                        $max_invoice_cnt = 0;
                    }
                    foreach($final_till_data[$store_code] as $k2 => $v2 ){
                        
                        if( $max_invoice_cnt > 0 ){
                            $utilization_value = round((( $v2 * 100 ) / $max_invoice_cnt));
                        }else{
                            $utilization_value = 0;
                        }
                        
                        if( $utilization_value <= $thresholdValue ) {
                            $final_till_data[$store_code]['utilization_percentage'][$k2] = $utilization_value;
                        }
                        
                        unset($final_till_data[$store_code][$k2]);
                    }
                    
                }                
            }
        }
        print_r($final_till_data); 
        print_r($trans1);
            print_r($trans2);
        
        
    }
}

