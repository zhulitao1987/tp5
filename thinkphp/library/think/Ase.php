<?php
namespace think;

class Ase{
    private static $privateKey='akD#K2$k=s2kh?DL';
    private static $iv='akD#K2$k=s2kh?DL';
    //加密
    public static function encrypt($data){
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128,Ase::$privateKey, $data, MCRYPT_MODE_CBC, Ase::$iv);
        return (base64_encode($encrypted));
    }

    public static function decrypt($data){
        $encryptedData = base64_decode($data);
        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, Ase::$privateKey, $encryptedData, MCRYPT_MODE_CBC,Ase::$iv);
        return ($decrypted);
    }

}
?>