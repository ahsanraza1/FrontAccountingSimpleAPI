<?php
namespace FAAPI;

$path_to_root = "..";

include_once($path_to_root . "/includes/ui/items_cart.inc");
// include_once($path_to_root . "/gl/includes/db/gl_journal.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/gl/includes/db/gl_db_banking.inc");

include_once($path_to_root . "/includes/data_checks.inc");

include_once($path_to_root . "/gl/includes/ui/gl_bank_ui.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/gl/includes/gl_ui.inc");
include_once($path_to_root . "/admin/db/attachments_db.inc");

class Payment
{

    public function post($rest)
    {
        global $Refs;
        $req = $rest->request();
        $model = $req->post();
        $_POST = $model;
        $today = date2sql(new_doc_date());
        $_POST["dimension_id"] = isset( $_POST["dimension_id"] )?$_POST["dimension_id"] :"";
        $_POST["dimension2_id"] = isset( $_POST["dimension2_id"] )?$_POST["dimension2_id"] :"";
        [$s, $m] = Base::validate($Refs, ST_BANKPAYMENT);
        if( !$s ) return $m;
        $cart = Base::create_cart(ST_BANKPAYMENT, 0);
        Base::handle_new_item();
        $transaction = write_bank_transaction(
            /*1*/ $_SESSION['pay_items']->trans_type, 
            /*2*/ $_SESSION['pay_items']->order_id, 
            /*3*/ $_POST['bank_account'],
            /*4*/ $_SESSION['pay_items'], 
            /*5*/ $_POST['date_'],
            /*6*/ $_POST['PayType'], 
            /*7*/ $_POST['person_id'], 
            /*8*/ get_post('PersonDetailID'),
            /*9*/ $_POST['reference'], 
            /*10*/ $_POST['memo_'], 
            /*11*/ true, 
            /*12*/ input_num('settled_amount', 
            /*13*/ null
         ));
         return api_response(200, ["data"=>Base::getBankTransaction($transaction)]);
         \api_create_response(array('id' => $transaction));
    }


    public function put($rest, $type, $trans)
    {
        $r = Base::get_voided_entry(ST_BANKPAYMENT, $trans);
        if( $r > 0 ){
            return api_response(404,["data"=>["message"=>"Transaction voided already"]]);
        }
        global $Refs;
        $req = $rest->request();
        $model = $req->post();
        $_POST = $model;
        // $cart = $this->create_cart(ST_BANKPAYMENT, 0);
        $_POST["dimension_id"] = isset( $_POST["dimension_id"] )?$_POST["dimension_id"]:"";
        $_POST["dimension2_id"] = isset( $_POST["dimension2_id"] )?$_POST["dimension2_id"]:"";
        [$s, $m] = Base::validate($Refs, ST_BANKPAYMENT, $trans);
        if( !$s ) return $m;
        $cart = Base::create_cart(ST_BANKPAYMENT, $trans);
        Base::handle_update_item();
        $id = write_bank_transaction(
            /*1*/ $_SESSION['pay_items']->trans_type, 
            /*2*/ $_SESSION['pay_items']->order_id, 
            /*3*/ $_POST['bank_account'],
            /*4*/ $_SESSION['pay_items'], 
            /*5*/ $_POST['date_'],
            /*6*/ $_POST['PayType'], 
            /*7*/ $_POST['person_id'], 
            /*8*/ get_post('PersonDetailID'),
            /*9*/ $_POST['ref'], 
            /*10*/ $_POST['memo_'], 
            /*11*/ true, 
            /*12*/ input_num('settled_amount', 
            /*13*/ null
         ));

        return \api_success_response([$id]);
    }

    public function delete($rest, $type, $trans)
    {
        $r = Base::get_voided_entry(ST_BANKPAYMENT, $trans);
        if( $r > 0 ){
            return api_response(404,["data"=>["message"=>"Transaction voided already"]]);
        }
        $msg = void_transaction($type, $trans, Today(), _("Document void by api."));
        return api_response(200, ["data"=> ["message"=>"Transaction voided"]]);
    }

}
