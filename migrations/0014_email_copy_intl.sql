UPDATE email_templates SET subject = 'Chào mừng đến với Crush',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">Chào mừng đến với Crush, {{name}}</h1><p>Tài khoản của bạn đã sẵn sàng. Đăng nhập và thêm vài thông tin dễ thương cho hồ sơ.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Đăng nhập</a></p><p style="color:#999;font-size:12px">Hoặc dán liên kết này: {{link}}</p></div>'
  WHERE `key` = 'welcome' AND lang = 'vi';

UPDATE email_templates SET subject = 'Bạn nhận được một lời mời hẹn hò',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{senderLabel}} đang thích bạn</h1><p>{{message}}</p><p>Nhấn vào nút bên dưới để chọn ngày, món ăn và nơi gặp nhau.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Mở lời mời</a></p><p style="color:#bbb;font-size:11px">Không quan tâm? Chặn và báo cáo: {{unsubscribe}}</p></div>'
  WHERE `key` = 'invite' AND lang = 'vi';

UPDATE email_templates SET subject = 'Crush에 오신 것을 환영합니다',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{name}}님, Crush에 오신 것을 환영합니다</h1><p>계정이 준비되었어요. 로그인하고 프로필을 예쁘게 채워 보세요.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">로그인</a></p><p style="color:#999;font-size:12px">또는 이 링크를 붙여넣으세요: {{link}}</p></div>'
  WHERE `key` = 'welcome' AND lang = 'ko';

UPDATE email_templates SET subject = '초대장이 도착했어요',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{senderLabel}}님이 당신을 좋아해요</h1><p>{{message}}</p><p>아래 버튼을 눌러 날짜와 메뉴, 만날 장소를 골라 주세요.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">초대장 열기</a></p><p style="color:#bbb;font-size:11px">관심이 없으신가요? 차단 및 신고: {{unsubscribe}}</p></div>'
  WHERE `key` = 'invite' AND lang = 'ko';
