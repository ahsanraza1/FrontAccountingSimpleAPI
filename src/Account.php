<?php
namespace FAAPI;

$path_to_root = "../..";

include_once($path_to_root . "/includes/ui/items_cart.inc");
// include_once($path_to_root . "/gl/includes/db/gl_journal.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/gl/includes/db/gl_db_banking.inc");

include_once($path_to_root . "/includes/data_checks.inc");

include_once($path_to_root . "/gl/includes/ui/gl_bank_ui.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/gl/includes/gl_ui.inc");
include_once($path_to_root . "/admin/db/attachments_db.inc");

class Account
{
    private $code = 200;
    public function get($rest, $type)
    {
        switch( $type)
        {
            case "bank":
                $out = $this->bankaccounts_all();
                break;
            case "infaq":
                $out = $this->getInfaqHeads();
                break;
            case "expense":
                $out = $this->getExpenseHeads();
                break;
            default:
                $this->code = 404;
                $out = ["message"=>"Invalid type of account!"];
                break;
        }      
        return api_response($this->code,["data"=>$out]);
        
    }

     private function bankaccounts_all($from = null)
    {
        if ($from == null) {
            $from = 0;
        }

        // TODO Paging doesn't work CP 2018-06
        // $sql = "SELECT * FROM " . TB_PREF . "bank_accounts LIMIT " . $from . ", " . RESULTS_PER_PAGE;
        $sql = "SELECT * FROM " . TB_PREF . "bank_accounts";
        $query = db_query($sql, "error");
        $info = array();
        while ($data = db_fetch($query, "error")) {
            $info[] = array(
                "id" => $data["id"],
                "account_type" => $data["account_type"],
                "account_code" => $data["account_code"],
                "bank_account_name" => $data["bank_account_name"],
                "bank_name" => $data["bank_name"],
                "bank_account_number" => $data["bank_account_number"],
                "bank_curr_code" => $data["bank_curr_code"],
                "bank_address" => $data["bank_address"],
                "dflt_curr_act" => $data["dflt_curr_act"]
            );
        }
        return $info;
    }

    public function getExpenseHeads()
    {
        return $this->glaccounts_all();
        
    }
    public function getInfaqHeads()
    {
        return $this->glaccounts_all();
        
    }
    private function glaccounts_all($from = null)
    {
        if ($from == null) {
            $from = 0;
        }

        // TODO Paging doesn't work CP 2018-06
        // $sql = "SELECT " . TB_PREF . "chart_master.*," . TB_PREF . "chart_types.name AS AccountTypeName FROM " . TB_PREF . "chart_master," . TB_PREF . "chart_types WHERE " . TB_PREF . "chart_master.account_type=" . TB_PREF . "chart_types.id ORDER BY account_code LIMIT " . $from . ", " . RESULTS_PER_PAGE;
        $sql = "SELECT " . TB_PREF . "chart_master.*," . TB_PREF . "chart_types.name AS AccountTypeName FROM " . TB_PREF . "chart_master," . TB_PREF . "chart_types WHERE " . TB_PREF . "chart_master.account_type=" . TB_PREF . "chart_types.id ORDER BY account_code";
        $query = db_query($sql, "error");
        $info = array();
        while ($data = db_fetch($query, "error")) {
            $info[] = array(
                'account_code' => $data['account_code'],
                'account_name' => $data['account_name'],
                'account_type' => $data['account_type'],
                'account_code2' => $data['account_code2']
            );
        }
        return $info;
    }

    public function getBalance($rest, $id)
    {
        $to = add_days(Today(), 1);
        $bal = get_balance_before_for_bank_account($id, $to);
        if( $bal == null){
            $this->code= 403;
            $resp = api_response($this->code,(["data"=>["message"=>"Bad request"]]));
        }else{
            $resp =  api_response($this->code,(["data"=>["balance"=>$bal]]));
        }
        return $resp;
    }


}
