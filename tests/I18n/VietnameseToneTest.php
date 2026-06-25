<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class VietnameseToneTest extends DatabaseTestCase
{
    public function test_sender_flow_uses_personal_genz_vietnamese(): void
    {
        $tr = new Translator($this->pdo(), 'vi');

        $this->assertSame('Rủ người ấy đi hẹn — ẩn danh mà vẫn cute.', $tr->t('Ask your crush out — stay anonymous, keep it cute.'));
        $this->assertSame('tên của bạn', $tr->t('your name'));
        $this->assertSame('email của bạn', $tr->t('your email'));
        $this->assertSame('email của bạn', $tr->t('you@email.com'));
        $this->assertSame('tạo mật khẩu', $tr->t('make a password'));
        $this->assertSame('tạo mật khẩu', $tr->t('pick a password'));
        $this->assertSame('mật khẩu', $tr->t('password'));
        $this->assertSame('Tạo lời mời', $tr->t('Start my invite'));
        $this->assertSame('Tạo mật khẩu để lát nữa quay lại dễ nha.', $tr->t('Make a password so you can hop back in later.'));
        $this->assertSame('Ủa Crush là gì?', $tr->t('Wait, what is Crush?'));
        $this->assertSame('Tạo mật khẩu để lần sau đăng nhập lại nha.', $tr->t('Pick a password — you\'ll use it to sign back in.'));
        $this->assertSame('email của người ấy', $tr->t('Their email'));
        $this->assertSame('tên người ấy', $tr->t('Their name'));
        $this->assertSame('Bạn muốn gửi cho người ấy kiểu nào?', $tr->t('How will you send it?'));
        $this->assertSame('Mình thoải mái — để người ấy chọn', $tr->t('I\'m open — they pick'));
        $this->assertSame('Người ấy đề xuất, mình chốt', $tr->t('They propose, I confirm'));
        $this->assertSame('Tạo lời mời cute này', $tr->t('Create my invite'));
        $this->assertSame('Ý', $tr->t('Italian'));
        $this->assertSame('Nhật', $tr->t('Japanese'));
        $this->assertSame('Xoá', $tr->t('Remove'));
    }

    public function test_responder_flow_avoids_formal_collective_voice(): void
    {
        $tr = new Translator($this->pdo(), 'vi');

        $this->assertSame('Chưa có phản hồi. Người ấy trả lời là mình báo bạn liền.', $tr->t('No response yet. We\'ll let you know the moment they answer.'));
        $this->assertSame('Không tìm thấy lời mời này rồi.', $tr->t('We couldn\'t find that invite.'));
        $this->assertSame('Muốn người ấy đón ở đâu? (không bắt buộc)', $tr->t('Where should they pick you up? (optional)'));
        $this->assertSame('làm mình bất ngờ đi', $tr->t('surprise me'));
    }
}
