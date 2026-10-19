<?php namespace Controllers;

require_once __DIR__ . '/../Views/view_helper.php';

class HomeController {
    public function index() {
        renderView('home/index.php');
    }

    /**
     * 利用規約画面の表示
     */
    public function terms() {
        renderView('legal/terms.php');
    }
    /**
     * ココフォリア用汎用出力ツールの表示
     */
    public function ccfolia() {
        renderView('tools/ccfolia.php');
    }
    // お問い合わせ画面の表示
    public function contact() {
        $data = [
            'success' => $_SESSION['contact_success'] ?? null,
            'error'   => $_SESSION['contact_error'] ?? null,
        ];
        unset($_SESSION['contact_success'], $_SESSION['contact_error']);
        renderView('home/contact.php', $data);
    }

    // お問い合わせ送信処理
    public function sendContact() {
        $email   = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($email) || empty($subject) || empty($message)) {
            $_SESSION['contact_error'] = 'すべての項目を入力してください。';
            header('Location: /contact');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['contact_error'] = '有効なメールアドレスを入力してください。';
            header('Location: /contact');
            exit;
        }

        // --- 管理者宛てメール送信設定 ---
        $to = getenv('ADMIN_EMAIL') ?: ($_ENV['ADMIN_EMAIL'] ?? 'default@example.com');  
        $mailSubject = "【デュエ筋 お問い合わせ】{$subject}";
        $mailBody = "以下の内容でお問い合わせを受け付けました。\n\n"
                  . "送信者: {$email}\n"
                  . "件名: {$subject}\n\n"
                  . "内容:\n{$message}\n";
        $headers = "From: {$email}\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8\r\n";

        if (mb_send_mail($to, $mailSubject, $mailBody, $headers)) {
            $_SESSION['contact_success'] = 'お問い合わせを送信しました。折り返しご連絡いたします。';
        } else {
            $_SESSION['contact_error'] = '送信に失敗しました。時間をおいて再度お試しください。';
        }

        header('Location: /contact');
        exit;
    }

    // 運営者情報の表示
    public function profile() {
        renderView('home/profile.php');
    }
}