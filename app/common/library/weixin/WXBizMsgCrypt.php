<?php

/**
 * 对公众平台发送给公众账号的消息加解密示例代码.
 *
 * @copyright Copyright (c) 1998-2014 Tencent Inc.
 */
namespace app\common\library\weixin;

class ErrorCode
{
	public static $OK = 0;
	public static $ValidateSignatureError = -40001;
	public static $ParseXmlError = -40002;
	public static $ComputeSignatureError = -40003;
	public static $IllegalAesKey = -40004;
	public static $ValidateAppidError = -40005;
	public static $EncryptAESError = -40006;
	public static $DecryptAESError = -40007;
	public static $IllegalBuffer = -40008;
	public static $EncodeBase64Error = -40009;
	public static $DecodeBase64Error = -40010;
	public static $GenReturnXmlError = -40011;
	public static $IllegalIv = -41002;
}

class SHA1
{
   
	public function getSHA1($token, $timestamp, $nonce, $encrypt_msg)
	{
		//ÅÅÐò
		try {
			$array = array($encrypt_msg, $token, $timestamp, $nonce);
			sort($array, SORT_STRING);
			$str = implode($array);
			return array(ErrorCode::$OK, sha1($str));
		} catch (Exception $e) {
			//print $e . "\n";
			return array(ErrorCode::$ComputeSignatureError, null);
		}
	}
}

class XMLParse
{
   
	public function extract($xmltext)
	{
		try {
			$xml = new \DOMDocument();
			$xml->loadXML($xmltext);
			$array_e = $xml->getElementsByTagName('Encrypt');
			$array_a = $xml->getElementsByTagName('ToUserName');
			$encrypt = $array_e->item(0)->nodeValue;
			$tousername = $array_a->item(0)->nodeValue;
			return array(0, $encrypt, $tousername);
		} catch (Exception $e) {
			//print $e . "\n";
			return array(ErrorCode::$ParseXmlError, null, null);
		}
	}
   
	public function generate($encrypt, $signature, $timestamp, $nonce)
	{
		$format = "<xml>
<Encrypt><![CDATA[%s]]></Encrypt>
<MsgSignature><![CDATA[%s]]></MsgSignature>
<TimeStamp>%s</TimeStamp>
<Nonce><![CDATA[%s]]></Nonce>
</xml>";
		return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
	}
}

class PKCS7Encoder
{
	public static $block_size = 32;
   
	function encode($text)
	{
		$block_size = PKCS7Encoder::$block_size;
		$text_length = strlen($text);
		//¼ÆËãÐèÒªÌî³äµÄÎ»Êý
		$amount_to_pad = PKCS7Encoder::$block_size - ($text_length % PKCS7Encoder::$block_size);
		if ($amount_to_pad == 0) {
			$amount_to_pad = PKCS7Encoder::block_size;
		}
		//»ñµÃ²¹Î»ËùÓÃµÄ×Ö·û
		$pad_chr = chr($amount_to_pad);
		$tmp = "";
		for ($index = 0; $index < $amount_to_pad; $index++) {
			$tmp .= $pad_chr;
		}
		return $text . $tmp;
	}
   
	function decode($text)
	{
		$pad = ord(substr($text, -1));
		if ($pad < 1 || $pad > 32) {
			$pad = 0;
		}
		return substr($text, 0, (strlen($text) - $pad));
	}
}

class Prpcrypt
{
	public $key;
	function __construct($k)
	{
		$this->key = base64_decode($k . "=");
	}

	/**
	 * 对明文进行加密
	 * @param string $text 需要加密的明文
	 * @return string 加密后的密文
	 */
	public function encrypt($text, $appid)
	{
	    try {
	        //获得16位随机字符串，填充到明文之前
	        $random = $this->getRandomStr();//"aaaabbbbccccdddd";
	        $text = $random . pack("N", strlen($text)) . $text . $appid;
	        $iv = substr($this->key, 0, 16);
	        $pkc_encoder = new PKCS7Encoder;
	        $text = $pkc_encoder->encode($text);
	        $encrypted = openssl_encrypt($text,'AES-256-CBC',substr($this->key, 0, 32),OPENSSL_ZERO_PADDING,$iv);
	        return array(ErrorCode::$OK, $encrypted);
	    } catch (Exception $e) {
	        //print $e;
	        return array(ErrorCode::$EncryptAESError, null);
	    }
	}
	/**
	 * 对密文进行解密
	 * @param string $encrypted 需要解密的密文
	 * @return string 解密得到的明文
	 */
	public function decrypt($encrypted, $appid)
	{
	    try {
	        $iv = substr($this->key, 0, 16);          
	        $decrypted = openssl_decrypt($encrypted,'AES-256-CBC',substr($this->key, 0, 32),OPENSSL_ZERO_PADDING,$iv);
	    } catch (Exception $e) {
	        return array(ErrorCode::$DecryptAESError, null);
	    }
	    try {
	        //去除补位字符
	        $pkc_encoder = new PKCS7Encoder;
	        $result = $pkc_encoder->decode($decrypted);
	        //去除16位随机字符串,网络字节序和AppId
	        if (strlen($result) < 16)
	            return "";
	        $content = substr($result, 16, strlen($result));
	        $len_list = unpack("N", substr($content, 0, 4));
	        $xml_len = $len_list[1];
	        $xml_content = substr($content, 4, $xml_len);
	        $from_appid = substr($content, $xml_len + 4);
	        if (!$appid)
	            $appid = $from_appid;
	        //如果传入的appid是空的，则认为是订阅号，使用数据中提取出来的appid
	    } catch (Exception $e) {
	        //print $e;
	        return array(ErrorCode::$IllegalBuffer, null);
	    }
	    if ($from_appid != $appid)
	        return array(ErrorCode::$ValidateAppidError, null);
	    //不注释上边两行，避免传入appid是错误的情况
	    return array(0, $xml_content, $from_appid); 
	    //增加appid，为了解决后面加密回复消息的时候没有appid的订阅号会无法回复
	}
	function getRandomStr()
	{
		$str = "";
		$str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
		$max = strlen($str_pol) - 1;
		for ($i = 0; $i < 16; $i++) {
			$str .= $str_pol[mt_rand(0, $max)];
		}
		return $str;
	}
}


class WXBizMsgCrypt
{
	private $token;
	private $encodingAesKey;
	private $appId;
   
	public function __construct($token, $encodingAesKey, $appId)
	{
		$this->token = $token;
		$this->encodingAesKey = $encodingAesKey;
		$this->appId = $appId;
	}
   
	public function encryptMsg($replyMsg, $timeStamp, $nonce, &$encryptMsg)
	{
		$pc = new Prpcrypt($this->encodingAesKey);
		//¼ÓÃÜ
		$array = $pc->encrypt($replyMsg, $this->appId);
		
		$ret = $array[0];
		if ($ret != 0) {
			return $ret;
		}
		if ($timeStamp == null) {
			$timeStamp = time();
		}
		$encrypt = $array[1];
		//Éú³É°²È«Ç©Ãû
		$sha1 = new SHA1;
		$array = $sha1->getSHA1($this->token, $timeStamp, $nonce, $encrypt);
		$ret = $array[0];
		if ($ret != 0) {
			return $ret;
		}
		$signature = $array[1];
		//Éú³É·¢ËÍµÄxml
		$xmlparse = new XMLParse;
		$encryptMsg = $xmlparse->generate($encrypt, $signature, $timeStamp, $nonce);
		return ErrorCode::$OK;
	}
   
	public function decryptMsg($msgSignature, $timestamp = null, $nonce, $postData, &$msg)
	{
		if (strlen($this->encodingAesKey) != 43) {
			return ErrorCode::$IllegalAesKey;
		}
		$pc = new Prpcrypt($this->encodingAesKey);
		//ÌáÈ¡ÃÜÎÄ
		$xmlparse = new XMLParse;
		$array = $xmlparse->extract($postData);
		$ret = $array[0];
		if ($ret != 0) {
			return $ret;
		}
		if ($timestamp == null) {
			$timestamp = time();
		}
		$encrypt = $array[1];
		$touser_name = $array[2];
		//ÑéÖ¤°²È«Ç©Ãû
		$sha1 = new SHA1;
		$array = $sha1->getSHA1($this->token, $timestamp, $nonce, $encrypt);
		$ret = $array[0];
		if ($ret != 0) {
			return $ret;
		}
		$signature = $array[1];
		if ($signature != $msgSignature) {
			return ErrorCode::$ValidateSignatureError;
		}
		$result = $pc->decrypt($encrypt, $this->appId);
		if ($result[0] != 0) {
			return $result[0];
		}
		$msg = $result[1];
		return ErrorCode::$OK;
	}
}
class WXBizDataCrypt
{
    private $appid;
    private $sessionKey;

    /**
     * 构造函数
     * @param $sessionKey string 用户在小程序登录后获取的会话密钥
     * @param $appid string 小程序的appid
     */
    public function __construct($appid, $sessionKey)
    {
        $this->appid = $appid;
        $this->sessionKey = $sessionKey;
        include_once __DIR__ . DIRECTORY_SEPARATOR . "errorCode.php";
    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $data string 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptData($encryptedData, $iv, &$data)
    {
        if (strlen($this->sessionKey) != 24) {
            return \ErrorCode::$IllegalAesKey;
        }
        $aesKey = base64_decode($this->sessionKey);
        if (strlen($iv) != 24) {
            return \ErrorCode::$IllegalIv;
        }
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $dataObj = json_decode($result);
        if ($dataObj == null) {
            return \ErrorCode::$IllegalBuffer;
        }
        if ($dataObj->watermark->appid != $this->appid) {
            return \ErrorCode::$IllegalBuffer;
        }
        $data = $result;
        return \ErrorCode::$OK;
    }

}

