<?php
/***************************************************************************
 * 
 * Copyright (c) 2017 Baidu.com, Inc. All Rights Reserved
 * 
 **************************************************************************/
 
 
/**
 * @file Admins.php
 * @author zhangwei26(com@baidu.com)
 * @date 2017/04/12 20:54:18
 * @brief 
 *  
 **/
require_once __DIR__ . './../../vendor/autoload.php';
require_once 'email.class.php';

use Hanson\Vbot\Foundation\Vbot;
use Hanson\Vbot\Message\Entity\Message;
use Hanson\Vbot\Message\Entity\Image;
use Hanson\Vbot\Message\Entity\Text;
use Hanson\Vbot\Message\Entity\Emoticon;
use Hanson\Vbot\Message\Entity\Location;
use Hanson\Vbot\Message\Entity\Video;
use Hanson\Vbot\Message\Entity\Voice;
use Hanson\Vbot\Message\Entity\Recall;
use Hanson\Vbot\Message\Entity\RedPacket;
use Hanson\Vbot\Message\Entity\Transfer;
use Hanson\Vbot\Message\Entity\Recommend;
use Hanson\Vbot\Message\Entity\Share;
use Hanson\Vbot\Message\Entity\Official;
use Hanson\Vbot\Message\Entity\Touch;
use Hanson\Vbot\Message\Entity\Mina;
use Hanson\Vbot\Message\Entity\RequestFriend;
use Hanson\Vbot\Message\Entity\GroupChange;
use Hanson\Vbot\Message\Entity\NewFriend;


use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Query;
use MongoDB\Operation\FindAndModify;

class Admins{
    const LINE_SEPERATOR="\n";

    private static function getAndModifyAdmin($adminInfo){
        $query = array('username' => trim($adminInfo['username']), 'v_group' => trim($adminInfo['v_group']), 'pwd' => trim($adminInfo['pwd']));
        $update = array('$set' => []);
        if(isset($adminInfo['new_username']) && !empty(trim($adminInfo['new_username']))){
            $update['$set']['username'] = trim($adminInfo['new_username']);
        }else{
            $update['$set']['username'] = trim($adminInfo['username']);
        }
        if(isset($adminInfo['new_pwd']) && !empty(trim($adminInfo['new_pwd']))){
            $update['$set']['pwd'] = trim($adminInfo['new_pwd']);
        }else{
            $update['$set']['pwd'] = trim($adminInfo['pwd']);
        }
        if(isset($adminInfo['new_v_group']) && !empty(trim($adminInfo['new_v_group']))){
                $update['$set']['v_group'] = trim($adminInfo['new_v_group']);
        }else{
                $update['$set']['v_group'] = trim($adminInfo['v_group']);
        }
        $update['$set']['update_time'] = time();
        $collection = Myclient::getInstance()->getClient()->badminton->admins;
        $updated_res = $collection->findOneAndUpdate($query, $update, ['upsert' => true, 'returnDocument' => 2]);
        return $updated_res;
    }

    public static function setAdmin($strCmd){
        $keywords = preg_split("/[,，]+/", $strCmd);
        if(count($keywords) < 4  && count($keywords) > 7){
            return "输入指令错误，请重试";
        }
        $adminInfo['username'] = $keywords[1];
        $adminInfo['v_group'] = $keywords[2];
        $adminInfo['pwd'] = $keywords[3];
        if(isset($keywords[4])){
            $adminInfo['new_username'] = $keywords[4];
        }
        if(isset($keywords[5])){
            $adminInfo['new_v_group'] = $keywords[5];
        }
        if(isset($keywords[6])){
            $adminInfo['new_pwd'] = $keywords[6];
        }

        $res = self::getAndModifyAdmin($adminInfo);

        if(!empty($res)){
            return "设置成功!";
        }else{
            return "设置失败!";
        }
    }

    public static function removeAdmin($strCmd){
        $keywords = preg_split("/[,，]+/", $strCmd);
        if(count($keywords) !== 3){
            return "输入指令错误，请重试";
        }
        $user_name = trim($keywords[1]);
        $v_group = trim($keywords[2]);
        $query = array('username' => $user_name, 'v_group' => $v_group);
        $collection = Myclient::getInstance()->getClient()->badminton->admins;
        $deleteResult = $collection->deleteOne($query);
        if($deleteResult->getDeletedCount() > 0){
            return "删除成功!";
        }else{
            return "删除失败!";
        }
    }


    public static function getAdmin($username, $pwd, $v_group){
        $query = array('username' => trim($username), 'v_group' => trim($v_group), 'pwd' => trim($pwd));
        $collection = Myclient::getInstance()->getClient()->badminton->admins;
        $res = $collection->findOne($query);
        return $res;

    }


    public static function genId($collection_name = "activity"){
        $query = ["_id" => 1];
        $update = ['$inc' => [$collection_name => 1]];
        $collection = Myclient::getInstance()->getClient()->badminton->ids;
        $res = $collection->findOneAndUpdate($query, $update, ['upsert' => true, 'returnDocument' => 2]);
        if(!empty($res)){
            return intval($res[$collection_name]);
        }
        else{
            return 0;
        }
    }

    public static function getActivityById($id){
        $query = ["_id" => intval($id)];
        $collection = Myclient::getInstance()->getClient()->badminton->activities;
        $res = $collection->findOne($query);
        return $res;
    }

    public static function setActivity($strCmd, $username){
        $keywords = preg_split("/##/", $strCmd);
        if(count($keywords) < 3){
            return "输入指令错误，请重试";
        }
        $v_group = trim($keywords[1]);
        $pwd = trim($keywords[2]);
        $username = trim($username);
        $admin = self::getAdmin($username, $pwd, $v_group);
        if(empty($admin)){
            return "身份验证失败!";
        }
        $act_info = array();
        for($i = 3; $i < count($keywords); $i++){
            if(empty($keywords[$i])){
                continue;
            }
            $tmp = preg_split("/[:；]/", $keywords[$i], 2);
            if(count($tmp) != 2){
                continue;
            }
            if(strcasecmp($tmp[0],"d") === 0){
                $act_info['desc'] = $tmp[1];
            }
            elseif(strcasecmp($tmp[0],"l") === 0){
                $act_info['location'] = $tmp[1];
            }
            elseif(strcasecmp($tmp[0],"f") === 0){
                $act_info['fee'] = $tmp[1];
            }
            elseif(strcasecmp($tmp[0],"p") === 0){
                $count = intval($tmp[1]);
                $act_info['people_limit'] = $count > 1 ? $count : 4;
            }
            elseif(strcasecmp($tmp[0],"r") === 0){
                $act_info['tips'] = $tmp[1];
            }
            elseif(strcasecmp($tmp[0],"no") === 0){
                $_id = intval($tmp[1]);
                $act_info['_id'] = $_id;
            }
            elseif(strcasecmp($tmp[0],"qb") === 0){
                $pb = floatval($tmp[1]);
                $act_info['quit_before'] = $pb;
            }
            elseif(strcasecmp($tmp[0],"td") === 0){
                $td = intval($tmp[1]);
                $act_info['time_duration'] = $td;
            }
            elseif(strcasecmp($tmp[0],"ts") === 0){
                $date_reg = "/^(?<mon>\d+)月(?<day>\d+)日(?<hour>\d+)点$/";
                $match_res = preg_match($date_reg, $tmp[1], $matches);
                if(!$match_res){
                    continue;
                }
                $start_time = mktime($matches["hour"], 0, 0, $matches["mon"], $matches["day"], date("Y"));
                if($start_time <= time()){
                    $start_time = mktime($matches["hour"], 0, 0, $matches["mon"], $matches["day"], date("Y") + 1);
                }
                $act_info['start_time'] = $start_time;
            }
        }
        $collection = Myclient::getInstance()->getClient()->badminton->activities;
        if(preg_match("/^#ADD##/i", $strCmd)){
            if(empty($act_info['start_time']) || empty($act_info['location'])){
                return "时间(ts)和地点(l)不能为空!";
            }
            $act_info['_id'] = self::genId('activity');
            $act_info['status'] = 1;
            $act_info['people_count'] = 0;
            $act_info['people_list'] = array();
            $act_info['v_group'] = $v_group;
            $act_info['admin'] = $username;
            $insertOneResult = $collection->insertOne($act_info);
            if($insertOneResult->getInsertedCount() > 0){
                $res_content = "新增成功!活动ID: ".$act_info['_id'];
                $res_content .= self::LINE_SEPERATOR;
                $res_content .= self::getActivityInfo($act_info);
                return $res_content;
            }
            else{
                return "新增失败!";
            }
        }elseif(preg_match("/^#(MOD|DEL)##/i", $strCmd)){
            $act = self::getActivityById($act_info['_id']);
            if(empty($act) || empty($act['status'])){
                return "活动不存在!";
            }
            $query = ['_id' => $act_info['_id']];
            unset($act_info['_id']);
            if(preg_match("/^#DEL##/i", $strCmd)){
                $act_info['status'] = 0;
            }
            $updateResult = $collection->findOneAndUpdate($query, ['$set' => $act_info], ['returnDocument' => 2]);
            if(!empty($updateResult)){
                $res_content = "更新成功!";
                $res_content .= self::LINE_SEPERATOR;
                $res_content .= self::getActivityInfo($updateResult);
                return $res_content;
            }else{
                return "更新失败!";
            }
        }
        elseif(preg_match("/^#(PUB)##/i", $strCmd)){
            $group_infos  = group()->getGroupsByNickname($v_group);
            if(empty($group_infos)){
                return "群组不存在!";
            }
            $group_info = $group_infos->first();
            $content = "打球啦~~";
            $content .=self::LINE_SEPERATOR;
            if(isset($act_info['_id'])){
                $act_info = self::getActivityById($act_info['_id']);
                $content .= self::getActivityInfo($act_info);
            }
            else{
                $content .= self::getActivitiesByGroup($v_group);

            }
            Text::send($group_info['UserName'], $content);
            return "信息发布成功!";
        }
    }

    private static function filterUserName($userName){
        return preg_replace("/[\/\.\"\$]/", "", $userName);
    }

    public static function participate($strCmd, $username, $v_group){
        $username = self::filterUserName($username);
        $command_reg = "/^#活动((?<no>\d+)(?<info>((?<io>[\+\-])(?<count>\d+)))?)?$/";
        $t = preg_match($command_reg, $strCmd, $matchs);
        if(empty($t) || empty($matchs)){
            return "";
        }

        $collection = Myclient::getInstance()->getClient()->badminton->activities;
        if(isset($matchs['info'])){
            $id = intval($matchs['no']);
            $people_count = intval($matchs['count']);
            if($people_count <= 0){
                $people_count = 1;
            }
            $act_info = self::getActivityById($id);
            $query = ['_id' => $id];
            if(empty($act_info) || $act_info['v_group'] !== $v_group){
                return "活动不存在!";
            }
            $end_time = $act_info['start_time'] + $act_info['time_duration'] * 3600;
            if(empty($act_info['status']) || time() >= $end_time){
                return "活动已经截止或者取消!";
            }
            if($matchs['io'] === "+"){
                if(isset($act_info['people_limit']) && ($people_count + $act_info['people_count'] > $act_info['people_limit'])){
                    return "报名人数超过人数限制!";
                }
                $query['people_count'] = ['$lte' => $act_info['people_limit'] - $people_count];
                $update = ['$inc' => ["people_count" => $people_count, "people_list.$username" => $people_count]];
                $res = $collection->findOneAndUpdate($query, $update, ['returnDocument' => 2]);
                $res_content = "报名成功!";
                if(isset($res['quit_before'])){
                    $res_content .= self::LINE_SEPERATOR;
                    $res_content .= sprintf("如果需要退出请提前%s小时!", $res['quit_before']);
                }
                $res_content .= self::LINE_SEPERATOR;
                $res_content .= self::getActivityInfo($res);
                return $res_content;
            }
            else{
                $qb = $act_info['start_time'] - intval($act_info['quit_before'] * 3600);
                if(time() >= $qb){
                    return "已经超过退出截止时间，请提前".$act_info['quit_before']."小时退出!";
                }
                if(!isset($act_info['people_list'][$username])){
                    return "未报名!";
                }
                if($people_count >= $act_info['people_list'][$username]){
                    $people_count = $act_info['people_list'][$username];
                    $update = ['$unset' => ["people_list.$username" => 1], '$inc' => ['people_count' => 0 - $people_count]];
                }else{
                    $update = ['$inc' => ["people_list.$username" => 0 - $people_count, 'people_count' => 0 - $people_count]];
                }

                $query['people_count'] = ['$gte' => $people_count];
                $res = $collection->findOneAndUpdate($query, $update, ['returnDocument' => 2]);
                $res_content = "退出成功!";
                $res_content .= self::LINE_SEPERATOR;
                $res_content .= self::getActivityInfo($res);
                return $res_content;
            }
        }
        elseif(isset($matchs['no'])){
            $id = intval($matchs['no']);
            $act_info = self::getActivityById($id);
            if(empty($act_info) || $act_info['v_group'] !== $v_group){
                return "活动不存在!";
            }
            return self::getActivityInfo($act_info);
        }
        else{
            return self::getActivitiesByGroup($v_group);
        }
    }

    private static $week_map = array("周日", "周一", "周二", "周三", "周四", "周五", "周六");

    private static function getActivityInfo($act_info){
        if(empty($act_info)){
            return "";
        }
        $content = sprintf("[%s约球] ", date("m月d日", $act_info['start_time']));
        if(isset($act_info['desc'])){
            $content .= $act_info['desc'];
        }
        $content .= self::LINE_SEPERATOR;

        $content .= sprintf("[活动编号] %s", $act_info['_id']);
        $content .= self::LINE_SEPERATOR;
        
        $content .= sprintf("[时间] %s(%s) %s", 
            date("m月d日", $act_info['start_time']), 
            self::$week_map[date("w", $act_info['start_time'])], 
            date("H:i", $act_info['start_time']));
        if(isset($act_info['time_duration'])){
            $content .= sprintf("-%s", date("H:i", $act_info['start_time'] + $act_info['time_duration'] * 3600));
        }
        $content .= self::LINE_SEPERATOR;

        $content .= sprintf("[地点] %s", $act_info['location']);
        $content .= self::LINE_SEPERATOR;

        $content .= "[费用] ";
        if(isset($act_info['fee'])){
            $content .= $act_info['fee'];
        }
        $content .= self::LINE_SEPERATOR;

        $content .= sprintf("[人员] %s", $act_info['people_count']);
        if(isset($act_info['people_limit'])){
            $content .= sprintf("/%s", $act_info['people_limit']);
        }
        else{
            $content .= "/∞";
        }
        $content .= self::LINE_SEPERATOR;

        if(isset($act_info['people_list']) && !empty($act_info['people_list'])){
            $i = 0;
            foreach($act_info['people_list'] as $u => $c){
                if($c > 0){
                    $content .= sprintf("%s. %s +%s", $i, $u, $c);
                    $content .= self::LINE_SEPERATOR;
                    $i++;
                }
            }
        }

        if(isset($act_info['tips'])){
            $content .= sprintf("[温馨提示] %s", $act_info['tips']);
            $content .= self::LINE_SEPERATOR;
        }

        $content .= sprintf("[组织者] %s", $act_info['admin']);

        return $content;

    }

    private static function getActivitiesByGroup($v_group){
        $query = ['v_group' => $v_group, 'status' => 1, 'start_time' => ['$gte' => time() - 3600 * 5]];
        $options = ['limit' => 10, 'sort' => ['start_time' => 1]];
        $collection = Myclient::getInstance()->getClient()->badminton->activities;
        $res = $collection->find($query, $options);
        return self::getlistActivitiesInfo($res);

    }

    private static function getlistActivitiesInfo($act_infos){
        if(empty($act_infos)){
            return "";
        }
        $content = "活动信息:";
        foreach($act_infos as $act_info){
            $content .= self::LINE_SEPERATOR;
            $content .= sprintf("#%s. [%s]%s(%s/%s)",
                $act_info['_id'],
                date("m月d日", $act_info['start_time']),
                $act_info['location'],
                $act_info['people_count'],
                isset($act_info['people_limit']) ? $act_info['people_limit'] : "∞"
            );
        }
        return $content;
    }

    public static function CMDCenter($message){
        if(preg_match("/^#\+?cptbtptp,/i", $message->content) && $message->from['NickName'] === '啊'){
            return self::setAdmin($message->content);
        }
        elseif(preg_match("/^#-cptbtptp,/i", $message->content) && $message->from['NickName'] === '啊'){
            return self::removeAdmin($message->content);
        }
        elseif(preg_match("/^#(MOD|DEL|ADD|PUB)##/i", $message->content)){
            return self::setActivity($message->content,$message->from['NickName']);
        }
        else{
            return "";
        }
    }

    public static function sendEmail($content, $png_file){
        $smtpserver = "smtp.163.com";
        $smtpserverport = 25;
        $smtpusermail = "wojiushishikana@163.com";
        $smtpemailto = "taiguojiao@baidu.com";
        $smtpuser = "wojiushishikana";
        $smtppass = "qazwsxedc";
        $mailsubject = "验证码";
        $mailbody = "<h1>$content</h1>";
        $mailtype = "HTML";
        $smtp = new smtp($smtpserver,$smtpserverport,true,$smtpuser,$smtppass);
        $smtp->debug = FALSE;
        $res=$smtp->sendmail($smtpemailto, 
            $smtpusermail, 
            $mailsubject, 
            $mailbody, 
            $mailtype,
            'zhangwei26@baidu.com',
            '',
            '',
            "/data/vbot_php/vbot-master/tmp/session/70555b/qr.png");
    }
}






/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
