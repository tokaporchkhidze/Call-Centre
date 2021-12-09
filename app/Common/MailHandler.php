<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 3/18/2019
 * Time: 12:45 PM
 */

namespace App\Common;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHandler {

    /**
     * @var PHPMailer
     */
    private $mail;

    /**
     * MailHandler constructor.
     */
    public function __construct() {
//        print("Mailer Consturcted\n");
        $this->mail = new PHPMailer(true);
    }

    public function configureSMTP($debugLevel=0) {
        $this->mail->isSMTP();
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $this->mail->SMTPDebug = $debugLevel;
        //Set the hostname of the mail server
        $this->mail->Host = '192.168.100.95';
        //Set the SMTP port number - likely to be 25, 465 or 587
        $this->mail->Port = 25;
        //Whether to use SMTP authentication
        $this->mail->SMTPAuth = true;
        //Username to use for SMTP authentication
        $this->mail->Username = 'accessreports';
        //Password to use for SMTP authentication
        $this->mail->Password = 'N42sE6fj';
        $this->mail->SMTPSecure = false;
        $this->mail->SMTPAutoTLS = false;
        return $this;
    }

    public function addRecipients(array $recievers) {
        $this->mail->setFrom('accessreports@silknet.com');
        foreach($recievers as $reciever) {
            $this->mail->addAddress($reciever);
        }
        $this->mail->isHTML(true);
        return $this;
    }

    public function addContent(string $subject, string $body, string $charSet = "UTF-8", string $encoding = "base64") {
        $this->mail->CharSet = $charSet;
        $this->mail->Encoding = $encoding;
        $this->mail->Subject = $subject;
        $this->mail->Body = $body;
        return $this;
    }

    public function sendMail() {
        $this->mail->send();
    }

}