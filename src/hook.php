<?php
date_default_timezone_set("asia/jakarta");

$HOOK = new Hook();
$HOOK->execute();

class Hook {
    private $telegramChatId = "";
    private $telegramBotToken = "";
    
    private $gitBranch = ["main"]; # for limitation event push
    private $gitToken = "";
    
    private $payload;
    
    public function __construct()
    {
        $this->payload = json_decode(file_get_contents("php://input"), true);
    }
    
    public function execute()
    {
        if ($this->authCheck()){
            if (isset($this->payload["object_kind"])){
                
                switch ($this->payload["object_kind"]){
                    case "push":
                        $message = $this->pushMessageGenerate();
                        break;
                    case "issue":
                        $message = $this->issueMessageGenerate();
                        break;
                    default:
                        $message = "Sorry, we just support event issue & push. ".json_encode($this->payload, JSON_PRETTY_PRINT);
                        break;
                }
                $this->sendMessage($message);
            }
        }else{
            $this->sendMessage("Missing or not authorize gitlab token ". json_encode(getallheaders(), JSON_PRETTY_PRINT));
        }
    }
    
    private function pushMessageGenerate()
    {
        $params = $this->payload;
        
        # get branch name
        $branchPath = explode("/", $params['ref']);
        $branchName = end($branchPath);
        if (in_array($branchName, $this->gitBranch)){
            $timestamp = date("Y-m-d H:i:s", strtotime($params['commits'][0]['timestamp']));
            
            $message = "==== [ 🚢 <b>New Commit</b> 🚀 ] =====\n";
            $message .= "Project : <b>{$params['project']['name']}</b>\n";
            $message .= "Author : <b>{$params['user_username']}</b>\n";
            $message .= "Branch : <b>".$branchName."</b>\n";
            $message .= "Message : <b>{$params['commits'][0]['message']}</b>\n";
            $message .= "Timestamp : <b>{$timestamp}</b>";
            
            return $message;
        }
        exit();
    }
    
    private function issueMessageGenerate()
    {
        $params = $this->payload;
        $status = $params['object_attributes']['state'];
        
        # just for a new issue, not for change
        if (!count($params["changes"]) || $status == "closed") {
            $timestamp = date("Y-m-d H:i:s", strtotime($params['object_attributes']['created_at']));
            $getLabels = function ($labels){
                $label = array_map(function ($r){
                    return $r["title"];
                }, $labels);
                return implode(", ", $label);
            };
            
            $getAssigns = function ($assigns){
                $assign = array_map(function ($r){
                    return $r["username"];
                }, $assigns);
                return implode(", ", $assign);
            };
            
            $messageTitle = $status == 'closed' ? 'Closed' :'Created New';
            $message = "==== [ 🐞 <b>{$messageTitle} Issue</b> ] =====\n";
            $message .= "Project : <b>{$params['project']['name']}</b>\n";
            $message .= "Author : <b>{$params['user']['username']}</b>\n";
            $message .= "Message : <b>{$params['object_attributes']['title']}</b>\n";
            $message .= "Timestamp : <b>{$timestamp}</b>\n";
            $message .= "Issue Label : <b>{$getLabels($params["labels"])}</b>\n";
            $message .= "Issue Assign : <b>{$getAssigns($params['assignees'])}</b>\n";
            $message .= "Issue Status : <b>{$params['object_attributes']['state']}</b>\n";
            $message .= "Issue URL : <b>{$params['object_attributes']['url']}</b>\n";
            
            return $message;
        }
        
        exit();
    }
    
    private function authCheck()
    {
        $headers = getallheaders();
        if (isset($headers["X-Gitlab-Token"]) && $headers["X-Gitlab-Token"] == $this->gitToken){
            return true;
        }
        return false;
    }
    
    private function sendMessage($message = "message not defined"){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage?chat_id={$this->telegramChatId}&parse_mode=html&text=".urlencode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        curl_exec($ch);
        curl_close($ch);
    }
}