<?php error_reporting(0); ?>
<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Monitoring extends CI_Controller
{
    // Kredensial BPJS, silakan edit sesuai kebutuhan
    private $api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';
    private $consid = '17432';
    private $secretkey = '3nK53BBE23';
    private $user_key = '1823bb1d8015aee02180ee12d2af2b2c';

    public function __construct()
    {
        parent::__construct();
        is_logged_in(); 
        
        $this->load->model("Antrol_model");
        $this->load->model("BpjsMonitorModel");
        $this->load->database();
        $this->load->library('form_validation');
    }
    
    public function Dashboard()
    {
        $data['title'] = 'Dashboard';
        $data['username'] = $this->db->get_where('tbl_user', ['username' => $this->session->userdata('username')])->row_array();
        

        $this->load->view('templates/header', $data);
        $this->load->view('templates/sidebar', $data);
        $this->load->view('templates/topbar', $data);
        $this->load->view('data/antrol', $data);
        $this->load->view('templates/footer');
    }

    public function getMonitorResponseTime()
    {
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));

        $endpoints = [
            'Diagnosa'      => $this->api_url . 'referensi/diagnosa/A00',
            'Poli'          => $this->api_url . 'referensi/poli/INT',
            'Faskes'        => $this->api_url . 'referensi/faskes/0101R001/1',
            'Dokter DPJP'   => $this->api_url . 'referensi/dokter/pelayanan/1/tglPelayanan/' . date('Y-m-d') . '/Spesialis/INT',
            'Propinsi'      => $this->api_url . 'referensi/propinsi',
            'Kabupaten'     => $this->api_url . 'referensi/kabupaten/propinsi/01',
            'Kecamatan'     => $this->api_url . 'referensi/kecamatan/kabupaten/0101',
            'Procedure'     => $this->api_url . 'referensi/procedure/001',
            'Kelas Rawat'   => $this->api_url . 'referensi/kelasrawat',
            'Dokter'        => $this->api_url . 'referensi/dokter/A',
            'Spesialistik'  => $this->api_url . 'referensi/spesialistik',
            'Ruang Rawat'   => $this->api_url . 'referensi/ruangrawat',
            'Cara Keluar'   => $this->api_url . 'referensi/carakeluar',
            'Pasca Pulang'  => $this->api_url . 'referensi/pascapulang',
            // Monitoring endpoint rujukan
            'Rujukan by NoRujukan'   => $this->api_url . 'Rujukan/170205010525Y000103', // Ganti dengan no rujukan dummy yang valid jika perlu
            'Rujukan by NoKartu'     => $this->api_url . 'Rujukan/Peserta/0002657364478', // Ganti dengan no kartu dummy yang valid jika perlu
            'Rujukan by TglRujukan'  => $this->api_url . 'Rujukan/List/TglRujukan/' . date('Y-m-d'), // Ganti dengan tanggal rujukan yang valid jika perlu
        ];

        $results = [];
        foreach ($endpoints as $name => $url) {
            $key = $this->consid . $this->secretkey . $tStamp;
            $start = microtime(true);
            $output = \Systems\Lib\BpjsService::get($url, NULL, $this->consid, $this->secretkey, $this->user_key, $tStamp);
            $end = microtime(true);
            $response_time = round(($end - $start) * 1000, 2); // ms
            $json = json_decode($output, true);
            $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : 'N/A';
            $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'No Response';
            $results[] = [
                'name' => $name,
                'url' => $url,
                'response_time' => $response_time,
                'code' => $code,
                'message' => $message
            ];
            usleep(200000); // 0.2 detik
        }

        echo "<h2>Monitoring Response Time BPJS VClaim (Referensi & Rujukan)</h2>";
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>Endpoint</th><th>URL</th><th>Waktu Response (ms)</th><th>Kode</th><th>Pesan</th></tr>";
        foreach ($results as $row) {
            echo "<tr>
                <td>{$row['name']}</td>
                <td style='font-size:10px'>{$row['url']}</td>
                <td>{$row['response_time']}</td>
                <td>{$row['code']}</td>
                <td>{$row['message']}</td>
            </tr>";
        }
        echo "</table>";
        echo "<br><b>Waktu Cek:</b> " . date('Y-m-d H:i:s');
        exit();
    }

    // Fungsi request BPJS langsung (tanpa library eksternal)
    private function bpjs_get($url, $consid, $secretkey, $user_key, $tStamp, &$curl_error = null)
    {
        $signature = base64_encode(hash_hmac('sha256', $consid . '&' . $tStamp, $secretkey, true));
        $headers = [
            'X-cons-id: ' . $consid,
            'X-timestamp: ' . $tStamp,
            'X-signature: ' . $signature,
            'user_key: ' . $user_key,
            'Content-Type:application/json',
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        return $output;
    }

    public function api_response_time()
    {
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));

        $endpoints = [
            'Diagnosa'      => $this->api_url . 'referensi/diagnosa/A00',
            'Poli'          => $this->api_url . 'referensi/poli/INT',
            'Faskes'        => $this->api_url . 'referensi/faskes/0101R001/1',
            'Dokter DPJP'   => $this->api_url . 'referensi/dokter/pelayanan/1/tglPelayanan/' . date('Y-m-d') . '/Spesialis/INT',
            'Propinsi'      => $this->api_url . 'referensi/propinsi',
            'Kabupaten'     => $this->api_url . 'referensi/kabupaten/propinsi/01',
            'Kecamatan'     => $this->api_url . 'referensi/kecamatan/kabupaten/0101',
            'Procedure'     => $this->api_url . 'referensi/procedure/001',
            'Kelas Rawat'   => $this->api_url . 'referensi/kelasrawat',
            'Dokter'        => $this->api_url . 'referensi/dokter/A',
            'Spesialistik'  => $this->api_url . 'referensi/spesialistik',
            'Ruang Rawat'   => $this->api_url . 'referensi/ruangrawat',
            'Cara Keluar'   => $this->api_url . 'referensi/carakeluar',
            'Pasca Pulang'  => $this->api_url . 'referensi/pascapulang',
            'Rujukan by NoRujukan'   => $this->api_url . 'Rujukan/170205010525Y000103',
            'Rujukan by NoKartu'     => $this->api_url . 'Rujukan/Peserta/0002657364478',
            'Rujukan by TglRujukan'  => $this->api_url . 'Rujukan/List/TglRujukan/' . date('Y-m-d'),
        ];

        $results = [];
        $success = 0;
        $error = 0;
        $total_response_time = 0;
        $alarms = [];
        foreach ($endpoints as $name => $url) {
            $start = microtime(true);
            $curl_error = null;
            $output = $this->bpjs_get($url, $this->consid, $this->secretkey, $this->user_key, $tStamp, $curl_error);
            $end = microtime(true);
            $response_time = round(($end - $start) * 1000, 2); // ms
            $json = json_decode($output, true);
            if ($curl_error) {
                $code = 'CURL_ERR';
                $message = $curl_error;
            } else {
                $code = isset($json['metaData']['code']) ? $json['metaData']['code'] : 'N/A';
                $message = isset($json['metaData']['message']) ? $json['metaData']['message'] : 'No Response';
            }
            $row = [
                'name' => $name,
                'url' => $url,
                'response_time' => $response_time,
                'code' => $code,
                'message' => $message
            ];
            $results[] = $row;

            $this->BpjsMonitorModel->insert_log([
                'endpoint' => $name,
                'url' => $url,
                'response_time' => $response_time,
                'code' => $code,
                'message' => $message,
                'polled_at' => date('Y-m-d H:i:s')
            ]);

            $total_response_time += $response_time;
            if ($code == 200 || $code == '200') {
                $success++;
            } else {
                $error++;
                $alarms[] = [
                    'name' => $name,
                    'message' => $message,
                    'code' => $code,
                    'time' => date('Y-m-d H:i:s')
                ];
                // Cek error berturut-turut dan trigger notifikasi
                $this->check_consecutive_errors($name, 3);
            }
            usleep(200000); // 0.2 detik
        }
        $total = count($results);
        $avg_response_time = $total > 0 ? round($total_response_time / $total, 2) : 0;
        $summary = [
            'total' => $total,
            'success' => $success,
            'error' => $error,
            'avg_response_time' => $avg_response_time
        ];
        $response = [
            'summary' => $summary,
            'detail' => $results,
            'alarms' => $alarms,
            'polled_at' => date('Y-m-d H:i:s')
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    public function monitor()
    {
        $data['title'] = 'Dashboard Monitoring BPJS';
        $data['username'] = $this->db->get_where('tbl_user', ['username' => $this->session->userdata('username')])->row_array();
        $this->load->view('templates/header', $data);
        $this->load->view('templates/sidebar', $data);
        $this->load->view('templates/topbar', $data);
        $this->load->view('monitoring/monitor', $data);
        $this->load->view('templates/footer');
    }

    // API: histori response time per endpoint
    public function api_response_time_history()
    {
        $endpoint = $this->input->get('endpoint');
        $start_date = $this->input->get('start_date');
        $end_date = $this->input->get('end_date');
        $interval = $this->input->get('interval') ?: 'day'; // day/week/month

        if (!$start_date || !$end_date) {
            http_response_code(400);
            echo json_encode(['error' => 'start_date, end_date required']);
            exit();
        }

        if ($endpoint) {
            $result = $this->BpjsMonitorModel->get_history($endpoint, $start_date, $end_date, $interval);
            $labels = [];
            $data = [];
            if ($interval == 'week') {
                foreach ($result as $row) {
                    $labels[] = $row['y'] . '-W' . str_pad($row['w'], 2, '0', STR_PAD_LEFT);
                    $data[] = round($row['avg_response_time'], 2);
                }
            } else {
                foreach ($result as $row) {
                    $labels[] = $row['label'];
                    $data[] = round($row['avg_response_time'], 2);
                }
            }
            header('Content-Type: application/json');
            echo json_encode([
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $endpoint,
                        'data' => $data
                    ]
                ]
            ]);
            exit();
        } else {
            // Semua endpoint sekaligus
            $result = $this->BpjsMonitorModel->get_history_all($start_date, $end_date, $interval);
            $label_map = [];
            $endpoint_map = [];
            if ($interval == 'week') {
                foreach ($result as $row) {
                    $label = $row['y'] . '-W' . str_pad($row['w'], 2, '0', STR_PAD_LEFT);
                    $label_map[$label] = true;
                    $endpoint_map[$row['endpoint']][$label] = round($row['avg_response_time'], 2);
                }
            } else {
                foreach ($result as $row) {
                    $label = $row['label'];
                    $label_map[$label] = true;
                    $endpoint_map[$row['endpoint']][$label] = round($row['avg_response_time'], 2);
                }
            }
            $labels = array_keys($label_map);
            sort($labels);
            $datasets = [];
            foreach ($endpoint_map as $endpoint => $data_per_label) {
                $data = [];
                foreach ($labels as $label) {
                    $data[] = isset($data_per_label[$label]) ? $data_per_label[$label] : null;
                }
                $datasets[] = [
                    'label' => $endpoint,
                    'data' => $data
                ];
            }
            header('Content-Type: application/json');
            echo json_encode([
                'labels' => $labels,
                'datasets' => $datasets
            ]);
            exit();
        }
    }

    // Fungsi untuk cek error berturut-turut dan trigger notifikasi
    private function check_consecutive_errors($endpoint, $threshold = 3)
    {
        $today = date('Y-m-d');
        $notif_key = 'notif_sent_' . md5($endpoint . $today);
        if ($this->session->userdata($notif_key)) return false;
        $rows = $this->BpjsMonitorModel->get_last_n_logs($endpoint, $threshold);
        if (count($rows) < $threshold) return false;
        $all_error = true;
        foreach ($rows as $row) {
            if ($row['code'] == '200' || $row['code'] == 200) {
                $all_error = false;
                break;
            }
        }
        if ($all_error) {
            // Kirim notifikasi WhatsApp
            $last = $rows[0];
            $msg = "[BPJS MONITOR]\nEndpoint: $endpoint\nError $threshold kali berturut-turut!\nWaktu: {$last['polled_at']}\nPesan: {$last['message']}";
            $this->sendWhatsAppMessage('6281256180502', $msg);
            $this->session->set_userdata($notif_key, true);
            return true;
        }
        return false;
    }

    // Kirim WhatsApp via Fonnte
    private function sendWhatsAppMessage($number, $message)
    {
        $token = 'nFi7goGNVJiG25gCbL7k'; // Ganti dengan token Fonnte Anda
        $url = 'https://api.fonnte.com/send';
        $data = [
            'target' => $number,
            'message' => $message,
            'countryCode' => '62',
        ];
        $headers = [
            'Authorization: ' . $token,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        log_message('info', 'Fonnte WA Notif: ' . $response);
    }
}



