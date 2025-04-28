<?php
namespace FAAPI;

class Base
{

    private function verify($data){
        $sql = "SELECT " . TB_PREF . "chart_master.* FROM " . TB_PREF . "chart_master WHERE " . TB_PREF . "chart_master.account_code='" . $data["code_id"]."' LIMIT 1";
        $query = db_query($sql, "error");
        if( db_num_rows($query, "error") <= 0 ) {
            return false;
        }

        $sql = "SELECT * FROM " . TB_PREF . "bank_accounts WHERE id='".$data["bank_account"]."'";
        $query = db_query($sql, "error");
        if( db_num_rows($query, "error") <= 0 ) {
            return false;
        }

        if( $_POST["dimension_id"] != ""){
            $sql = "SELECT * FROM ".TB_PREF."dimensions	WHERE id=".db_escape($_POST["dimension_id"]);
	        if(db_num_rows( db_query($sql, "error")) <=0){
                return false;
            }    
        }    
        if( $_POST["dimension2_id"] != ""){
            $sql = "SELECT * FROM ".TB_PREF."dimensions	WHERE id=".db_escape($_POST["dimension2_id"]);
	        if(db_num_rows( db_query($sql, "error")) <=0){
                return false;
            }    
        }    
        return true;
    }
    public static function validate($Refs, $current_type, $trans=null){
        $self = new self();
        if(
            !isset( $_POST["code_id"] ) ||
            !isset( $_POST["bank_account"] ) ||
            !isset( $_POST["PayType"] ) ||
            !isset( $_POST["amount"] ) ){
                return [false,api_response(403, ["data"=>["message"=>"Request must have code_id, bank_account, PayType and amount!"]])];
            }
       if(!$self->verify($_POST)){
           return [false,api_response(403, ["data"=>["message"=>"Invalid code_id, bank account or dimensions"]])];
       }
       if( !in_array( $_POST["PayType"], [0,2,3,4]) ){
           return [false,api_response(403, ["data"=>["message"=>"Invalid PayType"]])];
       }
       if( in_array($_POST["PayType"], [2, 3])){
           if( !isset($_POST["person_id"]) || !isset($_POST["PersonDetailID"]) ){
               return [false,api_response(403, ["data"=>["message"=>"Person detail is missing"] ])];
           }
           if( !is_numeric($_POST["person_id"]) || !is_numeric($_POST["PersonDetailID"]) ){
                return [false,api_response(403, ["data"=>["message"=>"Invalid person_id or PersonDetailID"] ])];
           }
       }
       if( floatval($_POST["amount"]) <= 0.0){
           return [false,api_response(403, ["data"=>["message"=>"Amount can't be 0"]])];
       }

       if( isset($_POST["reference"]) ){
            if(!$Refs->is_valid($_POST["reference"], $current_type)){
                return [false,api_response(403, ["data"=>["message"=>"Invalid Reference"]])];
            }elseif(!$Refs->is_new_reference($_POST["reference"], $current_type, $trans)){
                return [false,api_response(403, ["data"=>["message"=>"The entered reference is already in use ".($trans!=null?'in another transaction':'')."."]])];
            }    
        }
       return [true, true];
    }

    public static function get_voided_entry($type, $type_no)
    {
        $sql = "SELECT * FROM ".TB_PREF."voided WHERE type=".db_escape($type)
            ." AND id=".db_escape($type_no);

        $result = db_query($sql, "could not query voided transaction table");

        return db_num_rows($result);
    }
    public static function create_cart($type, $trans_no)
    {
        global $Refs;
    
        if (isset($_SESSION['pay_items']))
        {
            unset ($_SESSION['pay_items']);
        }
        $cart = new \items_cart($type);
        $cart->order_id = $trans_no;
    
        if ($trans_no) {
    
            $bank_trans = db_fetch(get_bank_trans($type, $trans_no));
            $_POST['bank_account'] = $_POST['bank_account'];//$bank_trans["bank_act"];
            // $_POST['PayType'] = $bank_trans["person_type_id"];
            $cart->reference = $bank_trans["ref"];
            
            if ($bank_trans["person_type_id"] == PT_CUSTOMER)
            {
                if( $_POST['PayType'] != $bank_trans["person_type_id"]){

                }else{
                    $trans = get_customer_trans($trans_no, $type);	
                    $_POST['person_id'] = $trans["debtor_no"];
                    $_POST['PersonDetailID'] = $trans["branch_code"];
                }
            }
            elseif ($bank_trans["person_type_id"] == PT_SUPPLIER)
            {
                $trans = get_supp_trans($trans_no, $type);
                $_POST['person_id'] = $trans["supplier_id"];
            }
            elseif ($bank_trans["person_type_id"] == PT_MISC)
            {
                if( $_POST['PayType'] != $bank_trans["person_type_id"]){
                    $bank_trans["person_id"] = $_POST['person_id'];
                }else{
                    $_POST['person_id'] = $bank_trans["person_id"];
                }

            }elseif ($bank_trans["person_type_id"] == PT_QUICKENTRY)
                $_POST['person_id'] = $bank_trans["person_id"];
            else 
                $_POST['person_id'] = $bank_trans["person_id"];
    
            $cart->memo_ = get_comments_string($type, $trans_no);
            if( !isset($_POST["date_"]) || trim( $_POST["date_"] )==""  ){
                $cart->tran_date = sql2date($bank_trans['trans_date']);
            }else{
                $cart->tran_date = $_POST["date_"];
            }
    
            $cart->original_amount = $bank_trans['amount'];
            $result = get_gl_trans($type, $trans_no);
            if ($result) {
                while ($row = db_fetch($result)) {
                    if (is_bank_account($row['account'])) {
                        // date exchange rate is currenly not stored in bank transaction,
                        // so we have to restore it from original gl amounts
                        $ex_rate = $bank_trans['amount']/$row['amount'];
                    } else {
                        $cart->add_gl_item( $row['account'], $row['dimension_id'],
                            $row['dimension2_id'], $row['amount'], $row['memo_']);
                    }
                }
            }
    
            // apply exchange rate
            foreach($cart->gl_items as $line_no => $line)
                $cart->gl_items[$line_no]->amount *= $ex_rate;

            if( $_POST['PayType'] != $bank_trans["person_type_id"]){
                $bank_trans["person_type_id"] = $_POST['PayType'];
            }
        } else {
            // echo json_encode($cart);
            $cart->reference = $Refs->get_next($cart->trans_type, null, $cart->tran_date);
            if( !isset($_POST["date_"]) || trim( $_POST["date_"] )==""  )
                $cart->tran_date = new_doc_date();
            else
                $cart->tran_date = $_POST["date_"];//new_doc_date();
            if (!is_date_in_fiscalyear($cart->tran_date))
                $cart->tran_date = end_fiscalyear();
            $cart->memo_ = $_POST['memo_'];
        }
        
        $_POST['memo_'] = $cart->memo_;
        $_POST['ref'] = $cart->reference;
        $_POST['date_'] = $cart->tran_date;
    
        $_SESSION['pay_items'] = &$cart;
        
        return $cart;
    }

    public static function handle_new_item()
    {
        if (! (new self() )->check_item_data())
            return;
        $amount = ($_SESSION['pay_items']->trans_type==ST_BANKPAYMENT ? 1:-1) * input_num('amount');
        $_SESSION['pay_items']->add_gl_item($_POST['code_id'], $_POST['dimension_id'],
            $_POST['dimension2_id'], $amount, $_POST['LineMemo']);

        // line_start_focus();
    }

    public static function handle_update_item()
    {
        $amount = ($_SESSION['pay_items']->trans_type==ST_BANKPAYMENT ? 1:-1) * input_num('amount');
        if((new self() )->check_item_data())
        {
            $_POST['Index'] = 0;
            $_SESSION['pay_items']->update_gl_item($_POST['Index'], $_POST['code_id'], 
                $_POST['dimension_id'], $_POST['dimension2_id'], $amount , $_POST['LineMemo']);
        }
        // line_start_focus();
    }

    function check_item_data()
    {
        if (!check_num('amount', 0))
        {
            display_error( _("The amount entered is not a valid number or is less than zero."));
            set_focus('amount');
            return false;
        }
        if (isset($_POST['_ex_rate']) && input_num('_ex_rate') <= 0)
        {
            display_error( _("The exchange rate cannot be zero or a negative number."));
            set_focus('_ex_rate');
            return false;
        }

        return true;
    }

    public static function getBankTransaction($transaction){
        $sql = "SELECT * FROM ".TB_PREF."bank_trans WHERE type={$transaction[0]} AND trans_no={$transaction[1]}";
         $result = db_query($sql, "");
         return db_fetch($result);
    }
}