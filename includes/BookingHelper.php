<?php
// ============================================================
// Booking Business Logic Helper
// ============================================================

class BookingHelper
{
    /**
     * Generate available time slots for a room on a given date
     */
    /**
     * Check if a date is a holiday
     */
    public static function isHoliday(string $date): bool
    {
        $db      = db();
        $monthDay = date('m-d', strtotime($date));
        $holiday = $db->fetch(
            "SELECT id FROM holidays WHERE is_active = 1 AND (
                (date = ? AND is_recurring = 0) OR
                (DATE_FORMAT(date, '%m-%d') = ? AND is_recurring = 1)
            )",
            [$date, $monthDay]
        );
        return $holiday !== null;
    }

    public static function getAvailableSlots(int $roomId, string $date, int $excludeBookingId = 0): array
    {
        $db = db();

        // Check if date is valid
        $ts = strtotime($date);
        if (!$ts) return [];

        $dayOfWeek = (int) date('w', $ts);

        // Get working hours for this day
        $wh = $db->fetch(
            "SELECT * FROM working_hours WHERE day_of_week = ? AND is_working_day = 1 AND is_active = 1",
            [$dayOfWeek]
        );
        if (!$wh) return []; // Not a working day

        // Check if date is a holiday
        $monthDay = date('m-d', $ts);
        $fullDate = date('Y-m-d', $ts);
        $holiday = $db->fetch(
            "SELECT id FROM holidays WHERE is_active = 1 AND (
                (date = ? AND is_recurring = 0) OR
                (DATE_FORMAT(date, '%m-%d') = ? AND is_recurring = 1)
            )",
            [$fullDate, $monthDay]
        );
        if ($holiday) return []; // Holiday

        // Get existing bookings for this room and date
        $excludeClause = $excludeBookingId > 0 ? " AND id != $excludeBookingId" : '';
        $bookedSlots = $db->fetchAll(
            "SELECT start_time, end_time FROM bookings
             WHERE room_id = ? AND booking_date = ? AND status IN ('pending', 'confirmed', 'completed')" . $excludeClause . "
             ORDER BY start_time ASC",
            [$roomId, $fullDate]
        );

        // Generate slots
        $slots       = [];
        $interval    = $wh['interval_minutes'] * 60; // in seconds
        $startTs     = strtotime($fullDate . ' ' . $wh['start_time']);
        $endTs       = strtotime($fullDate . ' ' . $wh['end_time']);
        $now         = time();

        $minBeforeMinutes = (int) getSetting('booking_min_minutes_before', '60');
        $minBookingTs     = $now + ($minBeforeMinutes * 60);

        for ($current = $startTs; $current < $endTs; $current += $interval) {
            $slotEnd  = $current + $interval;
            $slotStart = date('H:i:s', $current);
            $slotEndStr = date('H:i:s', $slotEnd);

            // Check overlap dengan booking existing di ruangan INI saja (prioritas atas past)
            $booked = false;
            foreach ($bookedSlots as $b) {
                if ($slotStart < $b['end_time'] && $slotEndStr > $b['start_time']) {
                    $booked = true;
                    break;
                }
            }

            // Slot sudah dipesan → merah (booked mengalahkan past)
            if ($booked) {
                $slots[] = [
                    'start'      => substr($slotStart, 0, 5),
                    'end'        => substr($slotEndStr, 0, 5),
                    'start_full' => $slotStart,
                    'end_full'   => $slotEndStr,
                    'available'  => false,
                    'reason'     => 'booked',
                    'label'      => substr($slotStart, 0, 5) . ' - ' . substr($slotEndStr, 0, 5),
                ];
                continue;
            }

            // Slot terlalu dekat/sudah lewat → abu-abu strikethrough
            if ($current < $minBookingTs && date('Y-m-d') === $fullDate) {
                $slots[] = [
                    'start'      => substr($slotStart, 0, 5),
                    'end'        => substr($slotEndStr, 0, 5),
                    'start_full' => $slotStart,
                    'end_full'   => $slotEndStr,
                    'available'  => false,
                    'reason'     => 'past',
                    'label'      => substr($slotStart, 0, 5) . ' - ' . substr($slotEndStr, 0, 5),
                ];
                continue;
            }

            $slots[] = [
                'start'      => substr($slotStart, 0, 5),
                'end'        => substr($slotEndStr, 0, 5),
                'start_full' => $slotStart,
                'end_full'   => $slotEndStr,
                'available'  => true,
                'reason'     => '',
                'label'      => substr($slotStart, 0, 5) . ' - ' . substr($slotEndStr, 0, 5),
            ];
        }

        return $slots;
    }

    /**
     * Check if a room is available for a time range
     */
    public static function isRoomAvailable(int $roomId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): bool
    {
        $db     = db();
        $params = [$roomId, $date, $endTime, $startTime];
        $extra  = '';

        if ($excludeBookingId) {
            $extra = ' AND id != ?';
            $params[] = $excludeBookingId;
        }

        $conflict = $db->fetch(
            "SELECT id FROM bookings
             WHERE room_id = ? AND booking_date = ? AND status IN ('pending', 'confirmed')
             AND start_time < ? AND end_time > ?" . $extra,
            $params
        );

        return $conflict === null;
    }

    /**
     * Get available date range for booking
     */
    public static function getBookingDateRange(): array
    {
        $allowSameDay = getSetting('booking_allow_same_day', '1') === '1';
        $minDate = $allowSameDay ? date('Y-m-d') : date('Y-m-d', strtotime('+1 day'));
        $maxDays = (int) getSetting('booking_max_days_advance', '30');
        $maxDate = date('Y-m-d', strtotime("+$maxDays days"));

        return ['min' => $minDate, 'max' => $maxDate];
    }

    /**
     * Check if booking date is valid (not holiday, working day, within range)
     */
    public static function isDateValid(string $date): array
    {
        $db = db();
        $ts = strtotime($date);

        if (!$ts) {
            return ['valid' => false, 'message' => 'Format tanggal tidak valid.'];
        }

        // Check same-day policy
        $today = date('Y-m-d');
        if ($date === $today && getSetting('booking_allow_same_day', '1') !== '1') {
            return ['valid' => false, 'message' => 'Booking pada hari yang sama tidak diizinkan.'];
        }

        // Check range
        $range = self::getBookingDateRange();
        if ($date < $range['min'] || $date > $range['max']) {
            return ['valid' => false, 'message' => 'Tanggal di luar rentang yang diizinkan.'];
        }

        // Check working day
        $dayOfWeek = (int) date('w', $ts);
        $wh = $db->fetch(
            "SELECT id FROM working_hours WHERE day_of_week = ? AND is_working_day = 1 AND is_active = 1",
            [$dayOfWeek]
        );
        if (!$wh) {
            $dayName = DAY_NAMES[$dayOfWeek] ?? 'Hari ini';
            return ['valid' => false, 'message' => "$dayName bukan hari kerja."];
        }

        // Check holiday
        $monthDay  = date('m-d', $ts);
        $fullDate  = date('Y-m-d', $ts);
        $holiday = $db->fetch(
            "SELECT name FROM holidays WHERE is_active = 1 AND (
                (date = ? AND is_recurring = 0) OR
                (DATE_FORMAT(date, '%m-%d') = ? AND is_recurring = 1)
            )",
            [$fullDate, $monthDay]
        );
        if ($holiday) {
            return ['valid' => false, 'message' => 'Tanggal ini adalah hari libur: ' . $holiday['name']];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Create a booking
     */
    public static function createBooking(array $data): array
    {
        $db = db();

        // Validate room
        $room = $db->fetch("SELECT * FROM rooms WHERE id = ? AND is_active = 1", [$data['room_id']]);
        if (!$room) {
            return ['success' => false, 'message' => 'Ruangan tidak ditemukan atau tidak aktif.'];
        }

        // Validate date
        $dateCheck = self::isDateValid($data['booking_date']);
        if (!$dateCheck['valid']) {
            return ['success' => false, 'message' => $dateCheck['message']];
        }

        // Validate capacity (done before lock — no shared-state involved)
        if (!empty($data['capacity_needed']) && $data['capacity_needed'] > $room['capacity']) {
            return ['success' => false, 'message' => "Kapasitas yang diminta melebihi kapasitas ruangan ({$room['capacity']} orang)."];
        }

        // Validate duration against booking_max_duration_hours
        $maxHours = (int) getSetting('booking_max_duration_hours', '8');
        if ($maxHours > 0) {
            $startTs   = strtotime($data['booking_date'] . ' ' . $data['start_time']);
            $endTs     = strtotime($data['booking_date'] . ' ' . $data['end_time']);
            $durationH = ($endTs - $startTs) / 3600;
            if ($durationH > $maxHours) {
                return ['success' => false, 'message' => "Durasi booking melebihi batas maksimum ({$maxHours} jam)."];
            }
        }

        // Generate booking code and token (before lock — no conflict-sensitive work here)
        $bookingCode = self::generateUniqueBookingCode();
        $token       = self::generateUniqueToken();

        // Determine status based on room settings
        $status = $room['requires_approval'] ? 'pending' : 'confirmed';

        // Token expiry: booking_date + token_expire_hours
        $expireHours = (int) getSetting('booking_token_expire_hours', '24');
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime($data['booking_date'] . ' 23:59:59') + ($expireHours * 3600));

        $bookingData = [
            'booking_code'    => $bookingCode,
            'room_id'         => (int) $data['room_id'],
            'department_id'   => !empty($data['department_id']) ? (int) $data['department_id'] : null,
            'meeting_type_id' => !empty($data['meeting_type_id']) ? (int) $data['meeting_type_id'] : null,
            'booker_name'     => $data['booker_name'],
            'booker_nik'      => $data['booker_nik'] ?? null,
            'booker_email'    => $data['booker_email'],
            'booker_phone'    => $data['booker_phone'] ?? null,
            'booking_date'    => $data['booking_date'],
            'start_time'      => $data['start_time'],
            'end_time'        => $data['end_time'],
            'capacity_needed' => !empty($data['capacity_needed']) ? (int) $data['capacity_needed'] : null,
            'purpose'         => $data['purpose'] ?? null,
            'notes_equipment' => $data['notes_equipment'] ?? null,
            'notes_other'     => $data['notes_other'] ?? null,
            'status'          => $status,
            'token'           => $token,
            'token_expires_at'=> $tokenExpiresAt,
        ];

        // ----------------------------------------------------------------
        // Advisory lock: prevents race condition when two requests arrive
        // simultaneously for the same room + date.  Lock is scoped to
        // room_id + booking_date so unrelated rooms never block each other.
        // ----------------------------------------------------------------
        $lockKey = 'rb_' . (int)$data['room_id'] . '_' . str_replace('-', '', $data['booking_date']);
        $locked  = (bool) $db->fetchColumn("SELECT GET_LOCK(?, 5)", [$lockKey]);

        if (!$locked) {
            return ['success' => false, 'message' => 'Sistem sedang memproses permintaan lain untuk ruangan ini. Silakan coba kembali.'];
        }

        try {
            // Authoritative availability check — inside the lock so the
            // check and insert are effectively atomic for this room+date.
            if (!self::isRoomAvailable($data['room_id'], $data['booking_date'], $data['start_time'], $data['end_time'])) {
                return ['success' => false, 'message' => 'Slot waktu ini baru saja diambil oleh pemesanan lain. Silakan pilih waktu yang berbeda atau muat ulang halaman.'];
            }

            $bookingId = $db->insert('bookings', $bookingData);

        } finally {
            // Always release the lock — even if an exception is thrown
            $db->fetchColumn("SELECT RELEASE_LOCK(?)", [$lockKey]);
        }

        return [
            'success'      => true,
            'booking_id'   => $bookingId,
            'booking_code' => $bookingCode,
            'token'        => $token,
            'status'       => $status,
            'room'         => $room,
        ];
    }

    /**
     * Process check-in for a booking
     */
    public static function checkIn(string $token, bool $byAdmin = false): array
    {
        $db = db();

        $booking = $db->fetch(
            "SELECT b.*, r.name as room_name, r.location, r.floor
             FROM bookings b JOIN rooms r ON b.room_id = r.id
             WHERE b.token = ?",
            [$token]
        );

        if (!$booking) {
            return ['success' => false, 'message' => 'Link check-in tidak valid.'];
        }

        if ($booking['status'] !== 'confirmed') {
            $statusMap = [
                'pending'   => 'Booking belum dikonfirmasi oleh admin.',
                'rejected'  => 'Booking telah ditolak.',
                'cancelled' => 'Booking telah dibatalkan.',
                'completed' => 'Booking sudah selesai.',
            ];
            return ['success' => false, 'message' => $statusMap[$booking['status']] ?? 'Status booking tidak valid.'];
        }

        if ($booking['checkin_at']) {
            return ['success' => false, 'message' => 'Anda sudah melakukan check-in sebelumnya.'];
        }

        // Check date (only on booking day)
        $today = date('Y-m-d');
        if ($booking['booking_date'] !== $today) {
            if ($booking['booking_date'] > $today) {
                return ['success' => false, 'message' => 'Check-in hanya bisa dilakukan pada hari booking (' . formatDateId($booking['booking_date']) . ').'];
            }
            return ['success' => false, 'message' => 'Waktu check-in sudah lewat.'];
        }

        // Check time window: open 30 min before start, expires 15 min after end
        // Admin bypass: $byAdmin = true skips time restriction
        if (!$byAdmin) {
            $nowTs         = time();
            $startTs       = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
            $endTs         = strtotime($booking['booking_date'] . ' ' . $booking['end_time']);
            $checkinOpenTs = $startTs - (30 * 60);
            $linkExpiresTs = $endTs  + (15 * 60);

            if ($nowTs < $checkinOpenTs) {
                return ['success' => false, 'message' => 'Check-in belum dibuka. Tersedia mulai pukul ' . date('H:i', $checkinOpenTs) . ' (30 menit sebelum booking).'];
            }
            if ($nowTs > $linkExpiresTs) {
                return ['success' => false, 'message' => 'Link check-in sudah kedaluwarsa (15 menit setelah jam selesai booking).'];
            }
        }

        // Update check-in time
        $db->update('bookings', ['checkin_at' => date('Y-m-d H:i:s')], ['token' => $token]);

        // Send notification
        Notification::createForAdmins(
            'checkin',
            'Check-in: ' . $booking['room_name'],
            $booking['booker_name'] . ' telah check-in di ' . $booking['room_name'] . ' (' . formatTimeRange($booking['start_time'], $booking['end_time']) . ')',
            $booking['id'],
            'checkin.manage'
        );

        return ['success' => true, 'booking' => $booking];
    }

    /**
     * Process check-out for a booking
     */
    public static function checkOut(string $token): array
    {
        $db = db();

        $booking = $db->fetch(
            "SELECT b.*, r.name as room_name
             FROM bookings b JOIN rooms r ON b.room_id = r.id
             WHERE b.token = ?",
            [$token]
        );

        if (!$booking) {
            return ['success' => false, 'message' => 'Link tidak valid.'];
        }

        if (!$booking['checkin_at']) {
            return ['success' => false, 'message' => 'Anda belum melakukan check-in.'];
        }

        if ($booking['checkout_at']) {
            return ['success' => false, 'message' => 'Anda sudah melakukan check-out sebelumnya.'];
        }

        // Update checkout and mark completed
        $db->update('bookings', [
            'checkout_at' => date('Y-m-d H:i:s'),
            'status'      => 'completed',
        ], ['token' => $token]);

        // Send notification
        Notification::createForAdmins(
            'checkout',
            'Check-out: ' . $booking['room_name'],
            $booking['booker_name'] . ' telah check-out dari ' . $booking['room_name'],
            $booking['id'],
            'checkin.manage'
        );

        return ['success' => true, 'booking' => $booking];
    }

    /**
     * Generate unique booking code
     */
    private static function generateUniqueBookingCode(): string
    {
        $db = db();
        do {
            $code = generateBookingCode();
        } while ($db->exists('bookings', ['booking_code' => $code]));
        return $code;
    }

    /**
     * Generate unique token
     */
    private static function generateUniqueToken(): string
    {
        $db = db();
        do {
            $token = generateToken();
        } while ($db->exists('bookings', ['token' => $token]));
        return $token;
    }

    /**
     * Create a booking from admin panel (bypasses date-range and min-minutes-before restrictions)
     */
    public static function createAdminBooking(array $data, ?string $recurringGroupId = null): array
    {
        $db = db();

        $room = $db->fetch("SELECT * FROM rooms WHERE id = ? AND is_active = 1", [$data['room_id']]);
        if (!$room) {
            return ['success' => false, 'message' => 'Ruangan tidak ditemukan atau tidak aktif.'];
        }

        $date = $data['booking_date'];
        $ts   = strtotime($date);

        // Must still be a working day
        $dayOfWeek = (int) date('w', $ts);
        $wh = $db->fetch(
            "SELECT id FROM working_hours WHERE day_of_week = ? AND is_working_day = 1 AND is_active = 1",
            [$dayOfWeek]
        );
        if (!$wh) {
            return ['success' => false, 'message' => $date . ': Bukan hari kerja.'];
        }

        // Must not be a holiday
        if (self::isHoliday($date)) {
            return ['success' => false, 'message' => $date . ': Hari libur.'];
        }

        if ($data['start_time'] >= $data['end_time']) {
            return ['success' => false, 'message' => 'Waktu selesai harus setelah waktu mulai.'];
        }

        if (!empty($data['capacity_needed']) && $data['capacity_needed'] > $room['capacity']) {
            return ['success' => false, 'message' => "Kapasitas melebihi kapasitas ruangan ({$room['capacity']} orang)."];
        }

        $bookingCode    = self::generateUniqueBookingCode();
        $token          = self::generateUniqueToken();
        $expireHours    = (int) getSetting('booking_token_expire_hours', '24');
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime($date . ' 23:59:59') + ($expireHours * 3600));

        $bookingData = [
            'booking_code'      => $bookingCode,
            'room_id'           => (int) $data['room_id'],
            'department_id'     => !empty($data['department_id'])   ? (int) $data['department_id']   : null,
            'meeting_type_id'   => !empty($data['meeting_type_id']) ? (int) $data['meeting_type_id'] : null,
            'booker_name'       => $data['booker_name'],
            'booker_nik'        => $data['booker_nik']   ?? null,
            'booker_email'      => $data['booker_email'],
            'booker_phone'      => $data['booker_phone'] ?? null,
            'booking_date'      => $date,
            'start_time'        => $data['start_time'],
            'end_time'          => $data['end_time'],
            'capacity_needed'   => !empty($data['capacity_needed']) ? (int) $data['capacity_needed'] : null,
            'purpose'           => $data['purpose']          ?? null,
            'notes_equipment'   => $data['notes_equipment']  ?? null,
            'notes_other'       => $data['notes_other']      ?? null,
            'status'            => 'confirmed', // admin-created bookings are auto-confirmed
            'token'             => $token,
            'token_expires_at'  => $tokenExpiresAt,
            'recurring_group_id'=> $recurringGroupId,
            'booked_by_admin'   => 1,
        ];

        $lockKey = 'rb_' . (int) $data['room_id'] . '_' . str_replace('-', '', $date);
        $locked  = (bool) $db->fetchColumn("SELECT GET_LOCK(?, 5)", [$lockKey]);

        if (!$locked) {
            return ['success' => false, 'message' => $date . ': Sistem sibuk, coba lagi.'];
        }

        try {
            if (!self::isRoomAvailable($data['room_id'], $date, $data['start_time'], $data['end_time'])) {
                return ['success' => false, 'message' => $date . ': Slot waktu sudah dipesan.'];
            }
            $bookingId = $db->insert('bookings', $bookingData);
        } finally {
            $db->fetchColumn("SELECT RELEASE_LOCK(?)", [$lockKey]);
        }

        Notification::createForAdmins(
            'new_booking',
            'Booking Admin: ' . $room['name'],
            Auth::user()['name'] . ' membuat booking ' . $room['name'] . ' pada ' . $date,
            $bookingId,
            'bookings.approve'
        );

        // Send confirmation email to booker (non-blocking — fire and forget)
        $newBooking = $db->fetch(
            "SELECT b.*, r.name as room_name FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ?",
            [$bookingId]
        );
        if ($newBooking) {
            Mailer::sendBookingConfirmation($newBooking);
        }

        return ['success' => true, 'booking_id' => $bookingId, 'booking_code' => $bookingCode];
    }

    /**
     * Generate list of booking dates for a recurring series.
     * $repeatType : 'weekly' | 'biweekly'
     * $daysOfWeek : array of int 0–6 (0=Sun)
     * $endType    : 'count' | 'date'
     * $endValue   : int (occurrences) or 'Y-m-d' string
     */
    public static function generateRecurringDates(
        string $startDate,
        string $repeatType,
        array  $daysOfWeek,
        string $endType,
               $endValue
    ): array {
        if (empty($daysOfWeek)) return [];

        sort($daysOfWeek);
        $stepDays = ($repeatType === 'biweekly') ? 14 : 7;
        $startTs  = strtotime($startDate);
        $maxCount = ($endType === 'count') ? max(1, (int) $endValue) : 200;
        $maxTs    = ($endType === 'date')  ? strtotime($endValue)     : PHP_INT_MAX;

        // Find the Sunday of the week containing $startDate
        $startDow     = (int) date('w', $startTs); // 0=Sun
        $weekCurrentTs = $startTs - ($startDow * 86400);

        $dates = [];
        $iterations = 0;

        while (count($dates) < $maxCount && $iterations < 500) {
            $iterations++;
            foreach ($daysOfWeek as $dow) {
                $d = $weekCurrentTs + ($dow * 86400);
                if ($d < $startTs) continue;
                if ($d > $maxTs)   return $dates;
                if (count($dates) >= $maxCount) return $dates;
                $dates[] = date('Y-m-d', $d);
            }
            $weekCurrentTs += ($stepDays * 86400);
        }

        return $dates;
    }

    /**
     * Get booking statistics for dashboard
     */
    public static function getStats(): array
    {
        $db        = db();
        $today     = date('Y-m-d');
        $thisMonth = date('Y-m');

        // Combine 4 booking aggregates into one query
        $row = $db->fetch(
            "SELECT
                SUM(booking_date = ?) AS total_today,
                SUM(status = 'pending') AS pending,
                SUM(status = 'confirmed' AND booking_date = ?) AS confirmed_today,
                SUM(DATE_FORMAT(booking_date, '%Y-%m') = ?) AS this_month
             FROM bookings",
            [$today, $today, $thisMonth]
        );

        return [
            'total_today'     => (int) ($row['total_today'] ?? 0),
            'pending'         => (int) ($row['pending'] ?? 0),
            'confirmed_today' => (int) ($row['confirmed_today'] ?? 0),
            'this_month'      => (int) ($row['this_month'] ?? 0),
            'total_rooms'     => (int) $db->fetchColumn("SELECT COUNT(*) FROM rooms WHERE is_active = 1"),
            'unread_notif'    => Notification::getUnreadCount(),
        ];
    }
}
