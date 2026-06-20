<?php
// =============================================
// Karavali Lodge — Admin Bridge API
// karavali_lodge/admin/api.php
// =============================================

header('Content-Type: application/json');

// ── CORS — restrict to same server only ──────────────────────────
// On localhost (XAMPP): requests come from http://localhost
// On live server: change ALLOWED_ORIGIN in config or .htaccess to
//                 your real domain e.g. https://yourdomain.com
//
// '*' (allow all) was removed — it lets any website on the internet
// call this API, which is a security risk even with session checks.

$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:80',
    'http://localhost:8080',
    'https://karavalilodge.freedev.app',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    // Known origin — allow with credentials
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    // Unknown origin — block cross-origin requests silently
    // (same-server requests from the admin panel have no Origin header
    //  and are always allowed through)
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../user/includes/config.php';

function genId() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
}

function nowIST() {
    return (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
}

function getPayload() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// Generic upsert helper
function upsert($db, $table, $id, $insertSql, $insertVals, $updateSql = null, $updateVals = null) {
    $chk = $db->prepare("SELECT id FROM $table WHERE id=?");
    $chk->execute([$id]);
    if ($chk->fetch()) {
        if ($updateSql) $db->prepare($updateSql)->execute($updateVals);
    } else {
        $db->prepare($insertSql)->execute($insertVals);
    }
}

// Generic delete helper — nullifies foreign keys first then deletes
function safeDelete($db, $table, $id, $fkUpdates = []) {
    foreach ($fkUpdates as $fkTable => $fkCol) {
        $db->prepare("UPDATE $fkTable SET $fkCol=NULL WHERE $fkCol=?")->execute([$id]);
    }
    $db->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db     = getDB();

// ── Authentication guard ──────────────────────────────────────────
// All actions require a valid admin session.
// The only exception is 'pull' which is also called by the admin JS
// auto-sync — it still requires a session, so unauthenticated browsers
// cannot read guest/booking data either.
//
// Write actions (save, delete, confirm, reject, cancel, reply, etc.)
// will return 401 Unauthorized to any unauthenticated caller.

// Every action — read or write — requires a valid admin session.
// This blocks unauthenticated browsers from accessing ANY data or
// making any changes via direct URL or API calls.
if ($action !== '' && !isAdminSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Admin login required.']);
    exit;
}

switch ($action) {

// ══════════════════════════════════════════════════════════════════
// PULL — MySQL → localStorage
// ══════════════════════════════════════════════════════════════════
case 'pull':
    $d = [];

    $d['rooms'] = array_map(fn($r) => [
        'id'=>$r['id'], 'roomNumber'=>$r['room_number'], 'roomType'=>$r['room_type'],
        'floor'=>$r['floor']??'', 'price'=>(float)$r['price'], 'capacity'=>(int)$r['capacity'],
        'status'=>$r['status'], 'amenities'=>$r['amenities']??'', 'description'=>$r['description']??'',
        'createdAt'=>$r['created_at'], 'updatedAt'=>$r['updated_at']??$r['created_at'],
    ], $db->query("SELECT * FROM rooms ORDER BY room_number")->fetchAll());

    $d['guests'] = array_map(fn($g) => [
        'id'=>$g['id'], 'name'=>$g['name'], 'mobile'=>$g['mobile'], 'email'=>$g['email']??'',
        'nationality'=>$g['nationality']??'Indian', 'address'=>$g['address']??'',
        'idProofType'=>$g['id_proof_type']??'', 'idProofNumber'=>$g['id_proof_number']??'',
        'createdAt'=>$g['created_at'],
    ], $db->query("SELECT * FROM guests ORDER BY name")->fetchAll());

    // ─── FIX: bookings — always include status, fall back to 'Pending' if NULL ───
    $d['bookings'] = array_map(fn($b) => [
        'id'          => $b['id'],
        'bookingNo'   => $b['booking_no'] ?? '',
        'guestName'   => $b['guest_name'],
        'mobile'      => $b['mobile'],
        'email'       => $b['email'] ?? '',
        'roomId'      => $b['room_id'] ?? '',
        'roomNumber'  => $b['room_number'] ?? '',
        'roomType'    => $b['room_type'] ?? '',
        'bookingType' => $b['booking_type'] ?? 'Online',
        'checkIn'     => $b['check_in'],
        'checkOut'    => $b['check_out'],
        'checkInTime' => $b['checkin_time']  ?? '',
        'checkOutTime'=> $b['checkout_time'] ?? '',
        'numGuests'   => (int)$b['num_guests'],
        // ← KEY FIX: coalesce NULL/empty status to 'Pending' so badge never renders blank
        'status'      => (isset($b['status']) && $b['status'] !== '') ? $b['status'] : 'Pending',
        'advance'     => (float)$b['advance'],
        'notes'       => $b['notes'] ?? '',
        'createdAt'   => $b['created_at'],
    ], $db->query("SELECT b.*, r.room_number FROM bookings b LEFT JOIN rooms r ON r.id = b.room_id ORDER BY b.created_at DESC")->fetchAll());

    $d['online_requests'] = array_map(fn($r) => [
        'id'=>'req_'.$r['id'], 'bookingNo'=>$r['request_no'], 'guestName'=>$r['guest_name'],
        'mobile'=>$r['mobile'], 'email'=>$r['email']??'', 'roomId'=>$r['room_id']??'',
        'roomNumber'=>$r['room_number']??'', 'roomType'=>$r['room_type']??'',
        'bookingType'=>'Online', 'checkIn'=>$r['check_in'], 'checkOut'=>$r['check_out'],
        'checkInTime'=>$r['checkin_time']??'', 'checkOutTime'=>$r['checkout_time']??'',
        'numGuests'=>(int)$r['num_adults']+(int)$r['num_children'],
        'status'=>$r['status'], 'advance'=>0, 'totalAmount'=>(float)$r['total_amount'],
        'paymentStatus'=>$r['payment_status']??'Pending',
        'paymentId'=>$r['payment_id']??'',
        'advancePaid'=>(float)($r['advance_paid']??0),
        'specialRequests'=>$r['special_requests']??'', 'createdAt'=>$r['created_at'], 'source'=>'online_request',
    ], $db->query("SELECT r.*, rm.room_number FROM online_booking_requests r LEFT JOIN rooms rm ON rm.id = r.room_id ORDER BY r.created_at DESC")->fetchAll());

    $d['checkins'] = array_map(fn($c) => [
        'id'=>$c['id'], 'guestName'=>$c['guest_name'], 'mobile'=>$c['mobile'],
        'roomId'=>$c['room_id']??'', 'roomNumber'=>$c['room_number']??'',
        'bookingId'=>$c['booking_id']??'',
        'checkInDate'=>$c['check_in_date'], 'checkInTime'=>$c['check_in_time']??'',
        'expectedCheckOut'=>$c['expected_check_out']??'', 'checkOutDate'=>$c['check_out_date']??'',
        'checkOutTime'=>$c['check_out_time']??'', 'idProofType'=>$c['id_proof_type']??'',
        'idNumber'=>$c['id_number']??'', 'advance'=>(float)$c['advance'],
        'numGuests'=>(int)$c['num_guests'], 'status'=>$c['status'], 'createdAt'=>$c['created_at'],
    ], $db->query("SELECT c.*, r.room_number FROM checkins c LEFT JOIN rooms r ON r.id = c.room_id ORDER BY c.created_at DESC")->fetchAll());

    // Ensure extra_charge and extra_note columns exist (added in bug-fix update)
    try {
        $db->exec("ALTER TABLE bills ADD COLUMN IF NOT EXISTS extra_charge decimal(10,2) DEFAULT 0.00 AFTER room_charges");
        $db->exec("ALTER TABLE bills ADD COLUMN IF NOT EXISTS extra_note varchar(300) DEFAULT '' AFTER extra_charge");
    } catch(Exception $e) { /* columns already exist */ }

    $d['bills'] = array_map(fn($b) => [
        'id'=>$b['id'], 'billNo'=>$b['bill_no'], 'checkinId'=>$b['checkin_id']??'',
        'guestId'=>$b['guest_id']??'', 'guestName'=>$b['guest_name'], 'mobile'=>$b['mobile']??'',
        'roomId'=>$b['room_id']??'', 'checkIn'=>$b['check_in'], 'checkOut'=>$b['check_out'],
        'nights'=>(int)$b['nights'], 'roomCharges'=>(float)$b['room_charges'],
        'extraCharge'=>(float)($b['extra_charge']??0), 'extraNote'=>$b['extra_note']??'',
        'serviceCharges'=>(float)$b['service_charges'], 'amenityCharges'=>(float)$b['amenity_charges'],
        'tax'=>(float)$b['tax'], 'totalAmount'=>(float)$b['total_amount'],
        'advance'=>(float)$b['advance'], 'balance'=>(float)$b['balance'],
        'status'=>$b['status'], 'createdAt'=>$b['created_at'],
    ], $db->query("SELECT * FROM bills ORDER BY created_at DESC")->fetchAll());

    $d['housekeeping'] = array_map(fn($h) => [
        'id'=>$h['id'], 'roomId'=>$h['room_id'], 'status'=>$h['status'],
        'assignedTo'=>$h['assigned_to']??'', 'priority'=>$h['priority']??'Normal',
        'assignedDate'=>$h['assigned_date']??'', 'completedAt'=>$h['completed_at']??'',
        'createdAt'=>$h['created_at'],
    ], $db->query("
        SELECT h.*
        FROM housekeeping h
        INNER JOIN (
            -- Latest record per room regardless of status —
            -- previously filtered WHERE status != 'Clean' which caused
            -- Clean rooms to vanish from the admin housekeeping list.
            -- Now shows ALL statuses; rooms not updated in 7 days are
            -- excluded to keep the list uncluttered.
            SELECT room_id, MAX(updated_at) AS latest
            FROM housekeeping
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY room_id
        ) latest_hk ON h.room_id = latest_hk.room_id AND h.updated_at = latest_hk.latest
        ORDER BY
            FIELD(h.status, 'Dirty', 'Cleaning', 'Maintenance', 'Clean'),
            h.updated_at DESC
    ")->fetchAll());

    $d['service_menu'] = array_map(fn($s) => [
        'id'=>$s['id'], 'name'=>$s['name'], 'category'=>$s['category']??'',
        'price'=>(float)$s['price'], 'createdAt'=>$s['created_at'],
    ], $db->query("SELECT * FROM service_menu ORDER BY category, name")->fetchAll());

    $d['guest_services'] = array_map(fn($g) => [
        'id'=>$g['id'], 'checkinId'=>$g['checkin_id']??'', 'serviceId'=>$g['service_id']??'',
        'serviceName'=>$g['service_name'], 'charge'=>(float)$g['charge'],
        'notes'=>$g['notes']??'', 'createdAt'=>$g['created_at'],
    ], $db->query("SELECT * FROM guest_services ORDER BY created_at DESC")->fetchAll());

    $d['room_service'] = array_map(fn($o) => [
        'id'=>$o['id'], 'orderNo'=>$o['order_no'], 'checkinId'=>$o['checkin_id']??'',
        'roomId'=>$o['room_id']??'', 'guestName'=>$o['guest_name']??'',
        'items'=>json_decode($o['items']??'[]',true)?:[], 'total'=>(float)$o['total'],
        'notes'=>$o['notes']??'', 'status'=>$o['status'], 'orderTime'=>$o['order_time']??'',
        'createdAt'=>$o['created_at'],
    ], $db->query("SELECT * FROM room_service ORDER BY created_at DESC")->fetchAll());

    $d['night_audits'] = array_map(fn($a) => [
        'id'=>$a['id'], 'date'=>$a['audit_date'], 'occupiedRooms'=>(int)$a['occupied_rooms'],
        'reservedRooms'=>(int)$a['reserved_rooms'], 'availableRooms'=>(int)$a['available_rooms'],
        'totalRooms'=>(int)$a['total_rooms'], 'occupancyRate'=>$a['occupancy_rate'],
        'roomRevenue'=>(float)$a['room_revenue'], 'serviceRevenue'=>(float)$a['service_revenue'],
        'amenityRevenue'=>(float)$a['amenity_revenue'], 'taxCollected'=>(float)$a['tax_collected'],
        'totalRevenue'=>(float)$a['total_revenue'], 'paidAmount'=>(float)$a['paid_amount'],
        'unpaidAmount'=>(float)$a['unpaid_amount'], 'totalOutstanding'=>(float)$a['total_outstanding'],
        'billCount'=>(int)$a['bill_count'], 'todayCheckIns'=>(int)$a['today_checkins'],
        'todayCheckOuts'=>(int)$a['today_checkouts'], 'newBookings'=>(int)$a['new_bookings'],
        'discrepancies'=>(int)$a['discrepancies'],
        'discrepancyDetails'=>json_decode($a['discrepancy_details']??'[]',true)?:[],
        'auditTime'=>$a['audit_time'], 'status'=>$a['status'], 'createdAt'=>$a['created_at'],
    ], $db->query("SELECT * FROM night_audits ORDER BY audit_date DESC")->fetchAll());

    // Ensure photo columns are LONGTEXT to hold base64 images without truncation
    try {
        $db->exec("ALTER TABLE id_proofs MODIFY COLUMN photo LONGTEXT, MODIFY COLUMN photo_back LONGTEXT");
    } catch(Exception $e) { /* already correct type */ }

    // Normalise photo value: base64 stays as-is, file paths become full URLs
    $normalisePhoto = function($val) {
        if (!$val) return null;
        if (str_starts_with($val, 'data:')) return $val;      // already base64
        if (str_starts_with($val, 'http')) {
            // Already a full URL — but if it points directly at uploads/id_proofs/
            // it will 403 (blocked by .htaccess). Rewrite to the admin photo viewer.
            if (str_contains($val, '/uploads/id_proofs/')) {
                $filename = basename($val);
                return ADMIN_URL . '/photo.php?f=' . urlencode($filename);
            }
            return $val;
        }
        // File path stored directly (e.g. 'uploads/id_proofs/front_xxx.jpg')
        // → serve via admin photo viewer (uploads/ is blocked by .htaccess
        // and only accessible to logged-in admins through this script)
        if (str_contains($val, 'id_proofs/')) {
            $filename = basename($val);
            return ADMIN_URL . '/photo.php?f=' . urlencode($filename);
        }
        return BASE_URL . '/' . ltrim($val, '/');
    };

    $d['hk_staff'] = array_map(fn($s) => [
        'id'         => $s['id'],
        'name'       => $s['name'],
        'category'   => $s['category'],
        'createdAt'  => $s['created_at'],
        'updatedAt'  => $s['updated_at'],
    ], $db->query("SELECT * FROM hk_staff ORDER BY created_at ASC")->fetchAll());

    $d['id_proofs'] = array_map(fn($p) => [
        'id'=>$p['id'], 'guestId'=>$p['guest_id'], 'idType'=>$p['id_type'],
        'idNumber'=>$p['id_number'], 'notes'=>$p['notes']??'', 'createdAt'=>$p['created_at'],
        'photo'     => $normalisePhoto($p['photo']),
        'photoBack' => $normalisePhoto($p['photo_back']),
    ], $db->query("SELECT id, guest_id, id_type, id_number, photo, photo_back, notes, created_at FROM id_proofs ORDER BY created_at DESC")->fetchAll());

    echo json_encode(['success'=>true, 'data'=>$d]);
    break;

// ══════════════════════════════════════════════════════════════════
// SAVE — Universal upsert for any record
// POST: { collection, record }
// ══════════════════════════════════════════════════════════════════
case 'save':
    $p          = getPayload();
    $collection = $p['collection'] ?? '';
    $rec        = $p['record']     ?? [];
    $id         = $rec['id']       ?? genId();
    $now        = nowIST();

    switch ($collection) {

        case 'rooms':
            upsert($db, 'rooms', $id,
                "INSERT INTO rooms(id,room_number,room_type,floor,price,capacity,status,amenities,created_at) VALUES(?,?,?,?,?,?,?,?,?)",
                [$id, $rec['roomNumber']??'', $rec['roomType']??'Single', $rec['floor']??'', $rec['price']??0, $rec['capacity']??2, $rec['status']??'Available', $rec['amenities']??'', $now],
                "UPDATE rooms SET room_number=?,room_type=?,floor=?,price=?,capacity=?,status=?,amenities=?,updated_at=? WHERE id=?",
                [$rec['roomNumber']??'', $rec['roomType']??'Single', $rec['floor']??'', $rec['price']??0, $rec['capacity']??2, $rec['status']??'Available', $rec['amenities']??'', $now, $id]
            );
            break;

        case 'guests':
            upsert($db, 'guests', $id,
                "INSERT INTO guests(id,name,mobile,email,nationality,address,id_proof_type,id_proof_number,created_at) VALUES(?,?,?,?,?,?,?,?,?)",
                [$id, $rec['name']??'', $rec['mobile']??'', $rec['email']??'', $rec['nationality']??'Indian', $rec['address']??'', $rec['idProofType']??'', $rec['idProofNumber']??'', $now],
                "UPDATE guests SET name=?,mobile=?,email=?,nationality=?,address=?,id_proof_type=?,id_proof_number=?,updated_at=? WHERE id=?",
                [$rec['name']??'', $rec['mobile']??'', $rec['email']??'', $rec['nationality']??'Indian', $rec['address']??'', $rec['idProofType']??'', $rec['idProofNumber']??'', $now, $id]
            );
            break;

        case 'bookings':
            $bn = $rec['bookingNo'] ?? ('BK-'.date('YmdHis').'-'.rand(1000,9999));
            $roomNum = $rec['roomNumber'] ?? '';
            if (!$roomNum && !empty($rec['roomId'])) {
                $rr = $db->prepare("SELECT room_number FROM rooms WHERE id=?");
                $rr->execute([$rec['roomId']]);
                $roomNum = $rr->fetchColumn() ?: '';
            }
            $bookingStatus = (isset($rec['status']) && $rec['status'] !== '') ? $rec['status'] : 'Pending';

            // ─── KEY FIX: If only status is being updated (no guestName sent),
            // do a status-only UPDATE to avoid wiping existing booking data ───
            $chkExist = $db->prepare("SELECT id FROM bookings WHERE id=?");
            $chkExist->execute([$id]);
            if ($chkExist->fetch() && empty($rec['guestName'])) {
                $db->prepare("UPDATE bookings SET status=?,updated_at=? WHERE id=?")
                   ->execute([$bookingStatus, $now, $id]);
            } else {
                upsert($db, 'bookings', $id,
                    "INSERT INTO bookings(id,booking_no,guest_name,mobile,email,room_id,room_number,room_type,booking_type,check_in,check_out,checkin_time,checkout_time,num_guests,status,advance,notes,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$id,$bn,$rec['guestName']??'',$rec['mobile']??'',$rec['email']??'',$rec['roomId']??null,$roomNum,$rec['roomType']??'',$rec['bookingType']??'Online',$rec['checkIn']??'',$rec['checkOut']??'',$rec['checkInTime']??'',$rec['checkOutTime']??'',$rec['numGuests']??1,$bookingStatus,$rec['advance']??0,$rec['notes']??'',$now],
                    "UPDATE bookings SET guest_name=?,mobile=?,room_id=?,room_number=?,room_type=?,booking_type=?,check_in=?,check_out=?,checkin_time=?,checkout_time=?,num_guests=?,status=?,advance=?,notes=?,updated_at=? WHERE id=?",
                    [$rec['guestName']??'',$rec['mobile']??'',$rec['roomId']??null,$roomNum,$rec['roomType']??'',$rec['bookingType']??'Online',$rec['checkIn']??'',$rec['checkOut']??'',$rec['checkInTime']??'',$rec['checkOutTime']??'',$rec['numGuests']??1,$bookingStatus,$rec['advance']??0,$rec['notes']??'',$now,$id]
                );
            }
            break;

        case 'checkins':
            $ciTime = nowIST();
            // Get room_number if not provided
            $ciRoomNum = $rec['roomNumber'] ?? '';
            if (!$ciRoomNum && !empty($rec['roomId'])) {
                $rr = $db->prepare("SELECT room_number FROM rooms WHERE id=?");
                $rr->execute([$rec['roomId']]);
                $ciRoomNum = $rr->fetchColumn() ?: '';
            }
            $checkinStatus = (isset($rec['status']) && $rec['status'] !== '') ? $rec['status'] : 'Checked-In';

            upsert($db, 'checkins', $id,
                "INSERT INTO checkins(id,guest_name,mobile,room_id,room_number,booking_id,check_in_date,check_in_time,expected_check_out,id_proof_type,id_number,advance,num_guests,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$id,$rec['guestName']??'',$rec['mobile']??'',$rec['roomId']??null,$ciRoomNum,$rec['bookingId']??null,$rec['checkInDate']??date('Y-m-d'),$ciTime,$rec['expectedCheckOut']??null,$rec['idProofType']??'',$rec['idNumber']??'',$rec['advance']??0,$rec['numGuests']??1,'Checked-In',$now],
                "UPDATE checkins SET status=?,check_out_date=?,check_out_time=?,updated_at=? WHERE id=?",
                [$checkinStatus,$rec['checkOutDate']??null,!empty($rec['checkOutDate']) ? $ciTime : null,$now,$id]
            );
            // Update room status
            if (!empty($rec['roomId'])) {
                $roomStatus = $checkinStatus === 'Checked-Out' ? 'Cleaning' : 'Occupied';
                $db->prepare("UPDATE rooms SET status=?,updated_at=? WHERE id=?")->execute([$roomStatus,$now,$rec['roomId']]);
            }
            // ─── FIX: also update the linked booking status in MySQL ───
            if (!empty($rec['bookingId'])) {
                $newBookingStatus = $checkinStatus === 'Checked-Out' ? 'Completed' : 'Checked-In';
                $db->prepare("UPDATE bookings SET status=?,updated_at=? WHERE id=?")
                   ->execute([$newBookingStatus, $now, $rec['bookingId']]);
            }
            break;

        case 'bills':
            $bn = $rec['billNo'] ?? ('INV-'.date('Ymd').'-'.rand(1000,9999));
            upsert($db, 'bills', $id,
                "INSERT INTO bills(id,bill_no,checkin_id,guest_name,mobile,room_id,check_in,check_out,nights,room_charges,extra_charge,extra_note,service_charges,amenity_charges,tax,total_amount,advance,balance,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$id,$bn,$rec['checkinId']??null,$rec['guestName']??'',$rec['mobile']??'',$rec['roomId']??null,$rec['checkIn']??'',$rec['checkOut']??'',$rec['nights']??1,$rec['roomCharges']??0,$rec['extraCharge']??0,$rec['extraNote']??'',$rec['serviceCharges']??0,$rec['amenityCharges']??0,$rec['tax']??0,$rec['totalAmount']??0,$rec['advance']??0,$rec['balance']??0,$rec['status']??'Unpaid',$now],
                "UPDATE bills SET status=?,balance=?,extra_charge=?,extra_note=?,nights=?,room_charges=?,service_charges=?,amenity_charges=?,tax=?,total_amount=?,advance=? WHERE id=?",
                [$rec['status']??'Unpaid',$rec['balance']??0,$rec['extraCharge']??0,$rec['extraNote']??'',$rec['nights']??1,$rec['roomCharges']??0,$rec['serviceCharges']??0,$rec['amenityCharges']??0,$rec['tax']??0,$rec['totalAmount']??0,$rec['advance']??0,$id]
            );
            break;

        case 'housekeeping':
            $assignedDate = !empty($rec['assignedDate']) ? $rec['assignedDate'] : date('Y-m-d');
            $completedAt  = !empty($rec['completedAt'])  ? $rec['completedAt']  : null;
            upsert($db, 'housekeeping', $id,
                "INSERT INTO housekeeping(id,room_id,status,assigned_to,priority,assigned_date,completed_at,created_at) VALUES(?,?,?,?,?,?,?,?)",
                [$id,$rec['roomId']??null,$rec['status']??'Dirty',$rec['assignedTo']??'',$rec['priority']??'Normal',$assignedDate,$completedAt,$now],
                "UPDATE housekeeping SET status=?,assigned_to=?,priority=?,assigned_date=?,completed_at=?,updated_at=? WHERE id=?",
                [$rec['status']??'Dirty',$rec['assignedTo']??'',$rec['priority']??'Normal',$assignedDate,$completedAt,$now,$id]
            );
            break;

        case 'room_service':
            // NOTE: room_service table has no updated_at column — do not
            // include it in the UPDATE, or PDO throws "column not found"
            // (caught silently by the frontend, leaving MySQL stale while
            // localStorage shows the update as successful).
            upsert($db, 'room_service', $id,
                "INSERT INTO room_service(id,order_no,checkin_id,room_id,guest_name,items,total,notes,status,order_time,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)",
                [$id,$rec['orderNo']??('RS-'.date('His').rand(100,999)),$rec['checkinId']??null,$rec['roomId']??null,$rec['guestName']??'',json_encode($rec['items']??[]),$rec['total']??0,$rec['notes']??'',$rec['status']??'Pending',$now,$now],
                "UPDATE room_service SET status=?,items=?,total=?,notes=? WHERE id=?",
                [$rec['status']??'Pending', json_encode($rec['items']??[]), $rec['total']??0, $rec['notes']??'', $id]
            );
            break;

        case 'guest_services':
            upsert($db, 'guest_services', $id,
                "INSERT INTO guest_services(id,checkin_id,service_id,service_name,charge,notes,created_at) VALUES(?,?,?,?,?,?,?)",
                [$id,$rec['checkinId']??null,$rec['serviceId']??null,$rec['serviceName']??'',$rec['charge']??0,$rec['notes']??'',$now],
                "UPDATE guest_services SET service_name=?,charge=?,notes=? WHERE id=?",
                [$rec['serviceName']??'',$rec['charge']??0,$rec['notes']??'',$id]
            );
            break;

        case 'service_menu':
            upsert($db, 'service_menu', $id,
                "INSERT INTO service_menu(id,name,category,price,created_at) VALUES(?,?,?,?,?)",
                [$id,$rec['name']??'',$rec['category']??'',$rec['price']??0,$now],
                "UPDATE service_menu SET name=?,category=?,price=? WHERE id=?",
                [$rec['name']??'',$rec['category']??'',$rec['price']??0,$id]
            );
            break;

        case 'night_audits':
            upsert($db, 'night_audits', $id,
                "INSERT INTO night_audits(id,audit_date,occupied_rooms,reserved_rooms,available_rooms,total_rooms,occupancy_rate,room_revenue,service_revenue,amenity_revenue,tax_collected,total_revenue,paid_amount,unpaid_amount,total_outstanding,bill_count,today_checkins,today_checkouts,new_bookings,discrepancies,discrepancy_details,audit_time,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$id,$rec['date']??date('Y-m-d'),$rec['occupiedRooms']??0,$rec['reservedRooms']??0,$rec['availableRooms']??0,$rec['totalRooms']??0,$rec['occupancyRate']??0,$rec['roomRevenue']??0,$rec['serviceRevenue']??0,$rec['amenityRevenue']??0,$rec['taxCollected']??0,$rec['totalRevenue']??0,$rec['paidAmount']??0,$rec['unpaidAmount']??0,$rec['totalOutstanding']??0,$rec['billCount']??0,$rec['todayCheckIns']??0,$rec['todayCheckOuts']??0,$rec['newBookings']??0,$rec['discrepancies']??0,json_encode($rec['discrepancyDetails']??[]),$rec['auditTime']??$now,$rec['status']??'Closed',$now],
                "UPDATE night_audits SET occupied_rooms=?,available_rooms=?,total_rooms=?,occupancy_rate=?,room_revenue=?,service_revenue=?,total_revenue=?,paid_amount=?,unpaid_amount=?,discrepancies=?,audit_time=?,status=? WHERE id=?",
                [$rec['occupiedRooms']??0,$rec['availableRooms']??0,$rec['totalRooms']??0,$rec['occupancyRate']??0,$rec['roomRevenue']??0,$rec['serviceRevenue']??0,$rec['totalRevenue']??0,$rec['paidAmount']??0,$rec['unpaidAmount']??0,$rec['discrepancies']??0,$rec['auditTime']??$now,$rec['status']??'Closed',$id]
            );
            break;

        case 'id_proofs':
            upsert($db, 'id_proofs', $id,
                "INSERT INTO id_proofs(id,guest_id,id_type,id_number,photo,photo_back,notes,created_at) VALUES(?,?,?,?,?,?,?,?)",
                // Store base64 as-is — do not manipulate photo data
                [$id,$rec['guestId']??null,$rec['idType']??'',$rec['idNumber']??'',$rec['photo']??null,$rec['photoBack']??null,$rec['notes']??'',$now],
                "UPDATE id_proofs SET id_type=?,id_number=?,photo=?,photo_back=?,notes=?,updated_at=? WHERE id=?",
                [$rec['idType']??'',$rec['idNumber']??'',$rec['photo']??null,$rec['photoBack']??null,$rec['notes']??'',$now,$id]
            );
            if (!empty($rec['guestId'])) {
                $db->prepare("UPDATE guests SET id_proof_type=?,id_proof_number=?,updated_at=? WHERE id=?")
                   ->execute([$rec['idType']??'',$rec['idNumber']??'',$now,$rec['guestId']]);
            }
            break;

        case 'hk_staff':
            upsert($db, 'hk_staff', $id,
                "INSERT INTO hk_staff(id,name,category,created_at,updated_at) VALUES(?,?,?,?,?)",
                [$id, $rec['name']??'', $rec['category']??'Cleaning', $now, $now],
                "UPDATE hk_staff SET name=?,category=?,updated_at=? WHERE id=?",
                [$rec['name']??'', $rec['category']??'Cleaning', $now, $id]
            );
            break;

        default:
            echo json_encode(['error'=>'Unknown collection: '.$collection]);
            exit;
    }

    echo json_encode(['success'=>true, 'id'=>$id]);
    break;

// ══════════════════════════════════════════════════════════════════
// DELETE — Universal delete for any record
// POST: { collection, id }
// ══════════════════════════════════════════════════════════════════
case 'delete':
    $p          = getPayload();
    $collection = $p['collection'] ?? '';
    $id         = $p['id']         ?? '';

    if (!$id) { echo json_encode(['error'=>'Missing id']); exit; }

    switch ($collection) {

        case 'rooms':
            // Block if room has active guests
            $chk = $db->prepare("SELECT id FROM checkins WHERE room_id=? AND status='Checked-In'");
            $chk->execute([$id]);
            if ($chk->fetch()) {
                echo json_encode(['error'=>'Cannot delete — room has active guests']);
                exit;
            }
            safeDelete($db, 'rooms', $id, [
                'bookings'     => 'room_id',
                'checkins'     => 'room_id',
                'bills'        => 'room_id',
                'housekeeping' => 'room_id',
                'room_service' => 'room_id',
            ]);
            break;

        case 'guests':
            safeDelete($db, 'guests', $id, ['id_proofs'=>'guest_id']);
            break;

        case 'bookings':
            $db->prepare("DELETE FROM bookings WHERE id=?")->execute([$id]);
            break;

        case 'service_menu':
            safeDelete($db, 'service_menu', $id, ['guest_services'=>'service_id']);
            break;

        case 'guest_services':
            $db->prepare("DELETE FROM guest_services WHERE id=?")->execute([$id]);
            break;

        case 'room_service':
            $db->prepare("DELETE FROM room_service WHERE id=?")->execute([$id]);
            break;

        case 'housekeeping':
            $db->prepare("DELETE FROM housekeeping WHERE id=?")->execute([$id]);
            break;

        case 'id_proofs':
            $db->prepare("DELETE FROM id_proofs WHERE id=?")->execute([$id]);
            break;

        case 'night_audits':
            $db->prepare("DELETE FROM night_audits WHERE id=?")->execute([$id]);
            break;

        case 'hk_staff':
            $db->prepare("DELETE FROM hk_staff WHERE id=?")->execute([$id]);
            break;

        default:
            echo json_encode(['error'=>'Unknown collection: '.$collection]);
            exit;
    }

    echo json_encode(['success'=>true]);
    break;

// ══════════════════════════════════════════════════════════════════
// RAZORPAY — CREATE ORDER
// POST: { amount_paise, receipt, notes }
// ══════════════════════════════════════════════════════════════════
case 'create_razorpay_order':
    $p      = getPayload();
    $amount = (int)($p['amount_paise'] ?? 0); // amount in paise (₹1 = 100 paise)
    if ($amount <= 0) { echo json_encode(['error' => 'Invalid amount']); exit; }

    $receipt = $p['receipt'] ?? ('rcpt_' . time());
    $notes   = $p['notes']   ?? [];

    // Razorpay Orders API
    $payload = json_encode([
        'amount'   => $amount,
        'currency' => RAZORPAY_CURRENCY,
        'receipt'  => $receipt,
        'notes'    => $notes,
        'payment_capture' => 1,
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['error' => 'cURL error: ' . $curlErr]);
        exit;
    }

    $order = json_decode($response, true);
    if ($httpCode !== 200 || empty($order['id'])) {
        echo json_encode(['error' => 'Razorpay order creation failed', 'details' => $order]);
        exit;
    }

    echo json_encode(['success' => true, 'order' => $order]);
    break;

// ══════════════════════════════════════════════════════════════════
// RAZORPAY — VERIFY PAYMENT SIGNATURE
// POST: { razorpay_order_id, razorpay_payment_id, razorpay_signature }
// ══════════════════════════════════════════════════════════════════
case 'verify_razorpay_payment':
    $p           = getPayload();
    $orderId     = $p['razorpay_order_id']  ?? '';
    $paymentId   = $p['razorpay_payment_id']  ?? '';
    $signature   = $p['razorpay_signature']  ?? '';

    if (!$orderId || !$paymentId || !$signature) {
        echo json_encode(['error' => 'Missing payment parameters']);
        exit;
    }

    // Verify signature: HMAC-SHA256 of "order_id|payment_id" with key_secret
    $expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);

    if (!hash_equals($expectedSig, $signature)) {
        echo json_encode(['success' => false, 'error' => 'Payment signature verification failed']);
        exit;
    }

    echo json_encode(['success' => true, 'verified' => true, 'payment_id' => $paymentId]);
    break;

// ══════════════════════════════════════════════════════════════════
// REJECT ONLINE REQUEST
// ══════════════════════════════════════════════════════════════════
case 'reject_request':
    $p     = getPayload();
    $reqId = $p['request_id'] ?? '';
    if (!$reqId) { echo json_encode(['error'=>'Missing request_id']); exit; }

    $now = nowIST();

    // 1. Mark request as Rejected
    $db->prepare("UPDATE online_booking_requests SET status='Rejected', updated_at=? WHERE id=?")
       ->execute([$now, $reqId]);

    // 2. Free the room — reset Reserved → Available
    //    Only resets if room is still Reserved (not if already Occupied by another guest)
    $db->prepare("
        UPDATE rooms SET status='Available', updated_at=?
        WHERE id = (SELECT room_id FROM online_booking_requests WHERE id=?)
        AND status = 'Reserved'
    ")->execute([$now, $reqId]);

    // 3. Also cancel any linked booking row that was created on confirmation
    $db->prepare("
        UPDATE bookings SET status='Cancelled', updated_at=?
        WHERE room_id = (SELECT room_id FROM online_booking_requests WHERE id=?)
        AND check_in  = (SELECT check_in  FROM online_booking_requests WHERE id=?)
        AND check_out = (SELECT check_out FROM online_booking_requests WHERE id=?)
        AND status IN ('Confirmed','Reserved','Pending')
    ")->execute([$now, $reqId, $reqId, $reqId]);

    echo json_encode(['success' => true]);
    break;

// ══════════════════════════════════════════════════════════════════
// CANCEL BOOKING BY GUEST (24-hour window)
// Called from my-bookings.php cancellation handler
// Also used by admin to see the cancellation in real-time
// ══════════════════════════════════════════════════════════════════
case 'cancel_booking':
    $p      = getPayload();
    $reqId  = $p['request_id'] ?? '';
    $mobile = $p['mobile']     ?? '';
    if (!$reqId) { echo json_encode(['error'=>'Missing request_id']); exit; }

    // Verify the request belongs to this guest and check 24hr window
    $stmt = $db->prepare("SELECT * FROM online_booking_requests WHERE id=? AND mobile=?");
    $stmt->execute([$reqId, $mobile]);
    $req  = $stmt->fetch();
    if (!$req) { echo json_encode(['error'=>'Request not found or not authorized']); exit; }

    // Check cancellation window for Confirmed bookings
    if ($req['status'] === 'Confirmed') {
        $bookedAt  = new DateTime($req['created_at'], new DateTimeZone('Asia/Kolkata'));
        $now       = new DateTime('now',              new DateTimeZone('Asia/Kolkata'));
        $diffHours = ($now->getTimestamp() - $bookedAt->getTimestamp()) / 3600;
        if ($diffHours > 24) {
            echo json_encode(['error' => 'Cancellation window expired (24 hours)', 'expired' => true]);
            exit;
        }
    } elseif ($req['status'] !== 'Pending') {
        echo json_encode(['error' => 'Cannot cancel booking with status: ' . $req['status']]);
        exit;
    }

    $now = nowIST();

    // 1. Cancel the online booking request
    $db->prepare("UPDATE online_booking_requests SET status='Cancelled', updated_at=? WHERE id=?")
       ->execute([$now, $reqId]);

    // 2. Cancel any linked booking in bookings table
    $linked = $db->prepare("SELECT id, room_id FROM bookings WHERE mobile=? AND check_in=? AND check_out=? AND status IN ('Confirmed','Reserved','Pending')");
    $linked->execute([$req['mobile'], $req['check_in'], $req['check_out']]);
    $linkedBooking = $linked->fetch();
    if ($linkedBooking) {
        $db->prepare("UPDATE bookings SET status='Cancelled', updated_at=? WHERE id=?")
           ->execute([$now, $linkedBooking['id']]);

        // 3. Free the room (set back to Available if it was Reserved)
        if ($linkedBooking['room_id']) {
            $db->prepare("UPDATE rooms SET status='Available', updated_at=? WHERE id=? AND status IN ('Reserved','Occupied','Cleaning')")
               ->execute([$now, $linkedBooking['room_id']]);
        }
    } elseif ($req['room_id']) {
        // Fallback: free room directly from request
        $db->prepare("UPDATE rooms SET status='Available', updated_at=? WHERE id=? AND status IN ('Reserved','Occupied','Cleaning')")
           ->execute([$now, $req['room_id']]);
    }

    echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    break;

// ══════════════════════════════════════════════════════════════════
// CONFIRM ONLINE REQUEST
// ══════════════════════════════════════════════════════════════════
case 'confirm_request':
    $p     = getPayload();
    $reqId = $p['request_id'] ?? '';
    if (!$reqId) { echo json_encode(['error'=>'Missing request_id']); exit; }

    $stmt = $db->prepare("SELECT * FROM online_booking_requests WHERE id=?");
    $stmt->execute([$reqId]);
    $req  = $stmt->fetch();
    if (!$req) { echo json_encode(['error'=>'Request not found']); exit; }

    $now = nowIST();
    $db->prepare("UPDATE online_booking_requests SET status='Confirmed', updated_at=? WHERE id=?")
       ->execute([$now, $reqId]);

    $bid       = genId();
    $bn        = 'BK-'.date('YmdHis').'-'.rand(1000,9999);
    $numGuests = (int)($req['num_adults']??1) + (int)($req['num_children']??0);

    // Get room_number from rooms table
    $roomNum = $req['room_number'] ?? '';
    if (!$roomNum && !empty($req['room_id'])) {
        $rr = $db->prepare("SELECT room_number FROM rooms WHERE id=?");
        $rr->execute([$req['room_id']]);
        $roomNum = $rr->fetchColumn() ?: '';
    }

    $db->prepare("INSERT INTO bookings(id,booking_no,guest_name,mobile,email,room_id,room_number,room_type,booking_type,check_in,check_out,checkin_time,checkout_time,num_guests,status,advance,payment_status,payment_id,notes,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Confirmed',?,?,?,?,?)")
       ->execute([$bid,$bn,$req['guest_name'],$req['mobile'],$req['email']??'',$req['room_id']??null,$roomNum,$req['room_type']??'','Online',$req['check_in'],$req['check_out'],
           $req['checkin_time']??'',
           $req['checkout_time']??'',
           $numGuests,
           (float)($req['advance_paid']??0),
           $req['payment_status']??'Pending',
           $req['payment_id']??null,
           $req['special_requests']??'',$now]);

    if (!empty($req['room_id'])) {
        $db->prepare("UPDATE rooms SET status='Reserved', updated_at=? WHERE id=?")->execute([$now, $req['room_id']]);
    }

    // ─── FIX: also auto-create guest record if they don't exist ───
    if (!empty($req['mobile'])) {
        $gchk = $db->prepare("SELECT id FROM guests WHERE mobile=?");
        $gchk->execute([$req['mobile']]);
        if (!$gchk->fetch()) {
            $gid = genId();
            $db->prepare("INSERT INTO guests(id,name,mobile,email,created_at) VALUES(?,?,?,?,?)")
               ->execute([$gid, $req['guest_name'], $req['mobile'], $req['email']??'', $now]);
        }
    }

    echo json_encode(['success'=>true, 'booking_no'=>$bn, 'booking_id'=>$bid]);
    break;

// ══════════════════════════════════════════════════════════════════
// LEGACY PUSH (kept for compatibility)
// ══════════════════════════════════════════════════════════════════
case 'push':
    echo json_encode(['success'=>true, 'message'=>'Use save/delete actions instead']);
    break;

// ── Get contact messages ─────────────────────────────────────────
case 'get_messages':
    $rows = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
    $msgs = array_map(fn($m) => [
        'id'         => $m['id'],
        'name'       => $m['name'],
        'email'      => $m['email']       ?? '',
        'mobile'     => $m['mobile']      ?? '',
        'subject'    => $m['subject']     ?? '',
        'message'    => $m['message'],
        'status'     => $m['status']      ?? 'New',
        'adminReply' => $m['admin_reply'] ?? '',
        'repliedAt'  => $m['replied_at']  ?? '',
        'repliedBy'  => $m['replied_by']  ?? '',
        'createdAt'  => $m['created_at'],
    ], $rows);
    echo json_encode(['success' => true, 'messages' => $msgs]);
    break;

// ── Update message status ─────────────────────────────────────────
case 'update_message':
    $p      = json_decode(file_get_contents('php://input'), true);
    $id     = $p['id']     ?? '';
    $status = $p['status'] ?? 'Read';
    if (!$id) { echo json_encode(['error'=>'Missing id']); exit; }
    $db->prepare("UPDATE contact_messages SET status=? WHERE id=?")->execute([$status, $id]);
    echo json_encode(['success' => true]);
    break;

// ── Delete message ────────────────────────────────────────────────
case 'delete_message':
    $p  = json_decode(file_get_contents('php://input'), true);
    $id = $p['id'] ?? '';
    if (!$id) { echo json_encode(['error'=>'Missing id']); exit; }
    $db->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    break;

// ── Reply to contact message ──────────────────────────────────────
case 'reply_message':
    $p     = json_decode(file_get_contents('php://input'), true);
    $id    = $p['id']    ?? '';
    $reply = trim($p['reply'] ?? '');
    $admin = $p['admin'] ?? 'Admin';
    if (!$id || !$reply) { echo json_encode(['error' => 'Missing id or reply text']); exit; }
    $db->prepare("UPDATE contact_messages SET admin_reply=?, replied_at=NOW(), replied_by=?, status='Read' WHERE id=?")
       ->execute([$reply, $admin, $id]);
    echo json_encode(['success' => true, 'replied_at' => date('Y-m-d H:i:s')]);
    break;

// ── Get logged-in admin info from session ────────────────────────
case 'get_admin':
    if (session_status() === PHP_SESSION_NONE) session_start();
    $adminId = $_SESSION['admin_id'] ?? null;
    if ($adminId) {
        $s = $db->prepare("SELECT id, name, mobile, email, role FROM admins WHERE id = ?");
        $s->execute([$adminId]);
        $admin = $s->fetch();
        if ($admin) {
            echo json_encode(['success' => true, 'admin' => $admin]);
            break;
        }
    }
    echo json_encode(['success' => false, 'admin' => null]);
    break;

default:
    echo json_encode(['error'=>'Unknown action. Use: pull | save | delete | confirm_request']);
}