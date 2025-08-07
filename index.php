<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>PHP 디지털 시계</title>
    <style>
        body {
            background: #f0f0f0;
            font-family: 'Arial', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .clock-container {
            background: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        #clock {
            font-size: 48px;
            font-weight: bold;
            color: #333;
        }
        .date {
            font-size: 20px;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <div class="clock-container">
        <div id="clock">
            <?php
                // PHP를 사용하여 서버의 현재 시간 설정
                echo date('H:i:s');
            ?>
        </div>
        <div class="date">
            <?php
                // PHP를 사용하여 현재 날짜 설정
                echo date('Y년 m월 d일');
            ?>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;

            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            document.querySelector('.date').textContent = `${year}년 ${month}월 ${day}일`;
        }

        // 1초마다 시계 업데이트
        setInterval(updateClock, 1000);

        // 페이지 로드 시 즉시 시계 업데이트
        updateClock();
    </script>

</body>
</html>
