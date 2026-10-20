<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * プロジェクトカード 99 枚（P001〜P099）の定義（002 §5）。
 */
final class ProjectCards
{
    /** @var array<string, array{string, string, string, string, int, int, int, string, string, string}> */
    private const ROWS = [
        'P001' => [
            '航空技師', 'Aviation Engineer', 'aviation_engineer',
            'CREW', 5, 2, 2, 'PLANNING_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P002' => [
            '航空指揮官', 'Aviation Commander', 'aviation_commander',
            'CREW', 5, 2, 2, 'PLANNING_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P003' => [
            'ロンサムラビット', 'Lonesome Rabbit', 'lonesome_rabbit',
            'CREW', 6, 1, 3, 'PLANNING_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P004' => [
            '軌道修正士', 'Course Corrector', 'course_corrector',
            'CREW', 3, 1, 2, 'BUDGET_PHASE', 'ACTIVATED', 'ANY',
        ],
        'P005' => [
            '調達担当官', 'Procurement Officer', 'procurement_officer',
            'CREW', 5, 2, 2, 'BUDGET_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P006' => [
            '予算折衝官', 'Budget Negotiator', 'budget_negotiator',
            'CREW', 7, 2, 2, 'BUDGET_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P007' => [
            '折衝術士', 'Master Negotiator', 'master_negotiator',
            'CREW', 9, 2, 4, 'BUDGET_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P008' => [
            '統合運用士', 'Integrated Operator', 'integrated_operator',
            'CREW', 10, 2, 2, 'BUDGET_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P009' => [
            'ファーストペンギン', 'First Penguin', 'first_penguin',
            'CREW', 3, 2, 2, 'DEPLOYED', 'ON_DEPLOY', 'ANY',
        ],
        'P010' => [
            '地上整備技師', 'Ground Mechanic', 'ground_mechanic',
            'CREW', 4, 1, 1, 'DEPLOYED', 'CONSTANT', 'ANY',
        ],
        'P011' => [
            '双配備技師', 'Twin Deploy Technician', 'twin_deploy_technician',
            'CREW', 4, 1, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P012' => [
            'ドッキング整備技師', 'Docking Mechanic', 'docking_mechanic',
            'CREW', 4, 2, 2, 'DEPLOYED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P013' => [
            'クルー指揮官', 'Crew Commander', 'crew_commander',
            'CREW', 7, 2, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P014' => [
            'クルー調整官', 'Crew Coordinator', 'crew_coordinator',
            'CREW', 7, 2, 2, 'DEPLOYED', 'CONSTANT', 'ANY',
        ],
        'P015' => [
            '機器搭載士', 'Device Installer', 'device_installer',
            'CREW', 7, 2, 2, 'DEPLOYED', 'CONSTANT', 'ANY',
        ],
        'P016' => [
            'システム・アーキテクト', 'System Architect', 'system_architect',
            'CREW', 7, 2, 2, 'DEPLOYED', 'CONSTANT', 'ANY',
        ],
        'P017' => [
            '主任機器士', 'Chief Device Officer', 'chief_device_officer',
            'CREW', 7, 3, 2, 'DEPLOYED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P018' => [
            'スクワドロン補佐官', 'Squadron Aide', 'squadron_aide',
            'CREW', 7, 3, 2, 'DEPLOYED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P019' => [
            'フリート指揮官', 'Fleet Commander', 'fleet_commander',
            'CREW', 7, 3, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P020' => [
            'フリートエンジニア', 'Fleet Engineer', 'fleet_engineer',
            'CREW', 7, 3, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P021' => [
            'フリートアーキテクト', 'Fleet Architect', 'fleet_architect',
            'CREW', 7, 3, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P022' => [
            'ジャグラー', 'Juggler', 'juggler',
            'CREW', 8, 3, 2, 'DEPLOYED', 'ON_DEPLOY', 'ANY',
        ],
        'P023' => [
            '統合プランナー', 'Integrated Planner', 'integrated_planner',
            'CREW', 9, 2, 2, 'DEPLOYED', 'ACTIVATED', 'ANY',
        ],
        'P024' => [
            'スワップ技師', 'Swap Technician', 'swap_technician',
            'CREW', 10, 2, 2, 'DEPLOYED', 'ON_DEPLOY', 'ANY',
        ],
        'P025' => [
            '先駆的ゴリラ', 'Pioneering Gorilla', 'pioneering_gorilla',
            'CREW', 10, 3, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P026' => [
            'フレンチブルドッグ', 'French Bulldog', 'french_bulldog',
            'CREW', 10, 6, 4, 'DEPLOYED', 'ON_DEPLOY', 'ANY',
        ],
        'P027' => [
            '人事プランナー', 'Personnel Planner', 'personnel_planner',
            'CREW', 11, 2, 3, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P028' => [
            '機器プランナー', 'Equipment Planner', 'equipment_planner',
            'CREW', 11, 2, 3, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P029' => [
            'ITプランナー', 'IT Planner', 'it_planner',
            'CREW', 11, 2, 3, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P030' => [
            '人材育成官', 'Talent Development Officer', 'talent_development_officer',
            'CREW', 12, 2, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P031' => [
            '機器投資官', 'Equipment Investment Officer', 'equipment_investment_officer',
            'CREW', 12, 2, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P032' => [
            'IT投資官', 'IT Investment Officer', 'it_investment_officer',
            'CREW', 12, 2, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P033' => [
            '第七王子', 'Seventh Prince', 'seventh_prince',
            'CREW', 8, 6, 3, 'DEPLOYED', 'CONSTANT', 'ANY',
        ],
        'P034' => [
            '加速ブースター', 'Acceleration Booster', 'acceleration_booster',
            'DEVICE', 5, 2, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'P035' => [
            'ギャンブルシステム', 'Gamble System', 'gamble_system',
            'DEVICE', 7, 0, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'P036' => [
            '軽量化フレーム', 'Lightweight Frame', 'lightweight_frame',
            'DEVICE', 7, 2, 2, 'LAUNCHED', 'ACTIVATED', 'ANY',
        ],
        'P037' => [
            '姿勢制御装置', 'Attitude Controller', 'attitude_controller',
            'DEVICE', 7, 3, 3, 'TURNED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P038' => [
            '燃料交換管', 'Fuel Transfer Pipe', 'fuel_transfer_pipe',
            'DEVICE', 7, 3, 3, 'LAUNCHED', 'ACTIVATED', 'ANY',
        ],
        'P039' => [
            'センサーアレイ', 'Sensor Array', 'sensor_array',
            'DEVICE', 9, 2, 3, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'P040' => [
            '射出補助装置', 'Launch Assist Unit', 'launch_assist_unit',
            'DEVICE', 9, 2, 3, 'LAUNCHED', 'CONSTANT', 'ANY',
        ],
        'P041' => [
            '打ち上げ契約装置', 'Launch Contract Unit', 'launch_contract_unit',
            'DEVICE', 12, 1, 3, 'LAUNCHED', 'ACTIVATED', 'ANY',
        ],
        'P042' => [
            'ダンスドッグロボット', 'Dancing Dog Robot', 'dancing_dog_robot',
            'DEVICE', 15, 2, 3, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'P043' => [
            '回生推進機', 'Regenerative Thruster', 'regenerative_thruster',
            'DEVICE', 5, 2, 3, 'TURNED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P044' => [
            '編隊同期装置', 'Formation Sync Unit', 'formation_sync_unit',
            'DEVICE', 6, 3, 3, 'TURNED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P045' => [
            '軌道整備ドッグ', 'Orbital Repair Dock', 'orbital_repair_dock',
            'DEVICE', 7, 2, 3, 'TURNED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P046' => [
            '固定軌道装置', 'Fixed Orbit Unit', 'fixed_orbit_unit',
            'DEVICE', 7, 5, 4, 'TURNED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P047' => [
            'トルネードビット', 'Tornado Bit', 'tornado_bit',
            'DEVICE', 9, 2, 3, 'TURNED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P048' => [
            '可変軌道装置', 'Variable Orbit Unit', 'variable_orbit_unit',
            'DEVICE', 5, 2, 3, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P049' => [
            '太陽電池パネル', 'Solar Panel', 'solar_panel',
            'DEVICE', 6, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P050' => [
            '欧阿中継ネットワーク', 'Euro-Africa Relay Network', 'euro_africa_relay_network',
            'DEVICE', 6, 1, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P051' => [
            '米亜中継ネットワーク', 'Americas-Asia Relay Network', 'americas_asia_relay_network',
            'DEVICE', 6, 1, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P052' => [
            '大陸アンテナ', 'Continental Antenna', 'continental_antenna',
            'DEVICE', 7, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P053' => [
            '面舵アンテナ', 'Starboard Antenna', 'starboard_antenna',
            'DEVICE', 7, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P054' => [
            '取舵アンテナ', 'Port Antenna', 'port_antenna',
            'DEVICE', 7, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P055' => [
            'ステルス艦', 'Stealth Ship', 'stealth_ship',
            'DEVICE', 7, 2, 3, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P056' => [
            '前方広域送信機', 'Forward Wide-Area Transmitter', 'forward_wide_area_transmitter',
            'DEVICE', 8, 2, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P057' => [
            '後方広域送信機', 'Rear Wide-Area Transmitter', 'rear_wide_area_transmitter',
            'DEVICE', 8, 2, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P058' => [
            '真紅遠隔通信装置', 'Crimson Remote Comm Unit', 'crimson_remote_comm_unit',
            'DEVICE', 8, 1, 3, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P059' => [
            '瑠璃遠隔通信装置', 'Lapis Remote Comm Unit', 'lapis_remote_comm_unit',
            'DEVICE', 8, 1, 3, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P060' => [
            '北洋波形転送装置', 'North Sea Waveform Relay', 'north_sea_waveform_relay',
            'DEVICE', 8, 2, 3, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P061' => [
            '南露波形転送装置', 'South Russia Waveform Relay', 'south_russia_waveform_relay',
            'DEVICE', 8, 2, 3, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P062' => [
            '塵芥海洋アンテナ', 'Dust Ocean Antenna', 'dust_ocean_antenna',
            'DEVICE', 6, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P063' => [
            '智利海洋アンテナ', 'Chile Ocean Antenna', 'chile_ocean_antenna',
            'DEVICE', 6, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P064' => [
            '前方ビームアンテナ', 'Forward Beam Antenna', 'forward_beam_antenna',
            'DEVICE', 10, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P065' => [
            '後方ビームアンテナ', 'Rear Beam Antenna', 'rear_beam_antenna',
            'DEVICE', 10, 2, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P066' => [
            '電波遮断装置', 'Signal Blocker', 'signal_blocker',
            'DEVICE', 10, 8, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P067' => [
            'チュリュモフ', 'Churyumov', 'churyumov',
            'SOFTWARE', 3, 2, 2, 'PROPOSAL_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P068' => [
            'シャノンエントロピー', 'Shannon Entropy', 'shannon_entropy',
            'SOFTWARE', 4, 2, 2, 'PROPOSAL_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P069' => [
            'ロスコスモス', 'Roscosmos', 'roscosmos',
            'SOFTWARE', 6, 2, 2, 'PROPOSAL_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P070' => [
            'スピンオフ', 'Spin-off', 'spin_off',
            'SOFTWARE', 6, 2, 2, 'PROPOSAL_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P071' => [
            'チャンドラセカール', 'Chandrasekhar', 'chandrasekhar',
            'SOFTWARE', 6, 2, 2, 'PROPOSAL_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P072' => [
            'ランチェスター', 'Lanchester', 'lanchester',
            'SOFTWARE', 6, 2, 3, 'PROPOSAL_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P073' => [
            'テスラメモリー', 'Tesla Memory', 'tesla_memory',
            'SOFTWARE', 8, 2, 3, 'PROPOSAL_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P074' => [
            'ゼロデイデプロイ', 'Zero-Day Deploy', 'zero_day_deploy',
            'SOFTWARE', 9, 3, 3, 'PROPOSAL_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P075' => [
            'ファラデー', 'Faraday', 'faraday',
            'SOFTWARE', 10, 1, 3, 'PROPOSAL_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P076' => [
            'グラウンドリンク', 'Ground Link', 'ground_link',
            'SOFTWARE', 10, 3, 3, 'PROPOSAL_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P077' => [
            'オライオンリワード', 'Orion Reward', 'orion_reward',
            'SOFTWARE', 7, 3, 2, 'ANYTIME', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P078' => [
            'アポロダウン', 'Apollo Down', 'apollo_down',
            'SOFTWARE', 3, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P079' => [
            'ゼログラビティα', 'Zero Gravity Alpha', 'zero_gravity_alpha',
            'SOFTWARE', 4, 1, 1, 'ORBIT_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P080' => [
            'ゼログラビティβ', 'Zero Gravity Beta', 'zero_gravity_beta',
            'SOFTWARE', 4, 1, 1, 'ORBIT_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P081' => [
            'ホーマンブースター', 'Hohmann Booster', 'hohmann_booster',
            'SOFTWARE', 4, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P082' => [
            'トライアドブースター', 'Triad Booster', 'triad_booster',
            'SOFTWARE', 4, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P083' => [
            'アルベドリフィル', 'Albedo Refill', 'albedo_refill',
            'SOFTWARE', 4, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P084' => [
            'ジオサーマルリフィル', 'Geothermal Refill', 'geothermal_refill',
            'SOFTWARE', 4, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P085' => [
            'ケスラー回避システム', 'Kessler Avoidance System', 'kessler_avoidance_system',
            'SOFTWARE', 4, 2, 2, 'ORBIT_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P086' => [
            'データダウンリンク', 'Data Downlink', 'data_downlink',
            'SOFTWARE', 4, 2, 3, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P087' => [
            'データローリング', 'Data Rolling', 'data_rolling',
            'SOFTWARE', 4, 2, 3, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P088' => [
            'ハイゲンスダウン', 'Huygens Down', 'huygens_down',
            'SOFTWARE', 5, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P089' => [
            'ランディングダウン', 'Landing Down', 'landing_down',
            'SOFTWARE', 5, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P090' => [
            'ギムバル制御', 'Gimbal Control', 'gimbal_control',
            'SOFTWARE', 5, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P091' => [
            'カスケードブースト', 'Cascade Boost', 'cascade_boost',
            'SOFTWARE', 5, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P092' => [
            'スプラッシュダウン', 'Splashdown', 'splashdown',
            'SOFTWARE', 6, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P093' => [
            'オービタル72', 'Orbital 72', 'orbital_72',
            'SOFTWARE', 6, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P094' => [
            'デコンフリクト', 'Deconflict', 'deconflict',
            'SOFTWARE', 6, 2, 2, 'ORBIT_PHASE', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'P095' => [
            'フリップマニューバ', 'Flip Maneuver', 'flip_maneuver',
            'SOFTWARE', 7, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P096' => [
            'アルファリンク', 'Alpha Link', 'alpha_link',
            'SOFTWARE', 7, 2, 3, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P097' => [
            'ベータリンク', 'Beta Link', 'beta_link',
            'SOFTWARE', 7, 2, 3, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P098' => [
            'バックリンク', 'Back Link', 'back_link',
            'SOFTWARE', 9, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'P099' => [
            'フライバイリンク', 'Flyby Link', 'flyby_link',
            'SOFTWARE', 11, 2, 2, 'ORBIT_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
    ];

    /** @var array<string, ProjectCardDefinition>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, ProjectCardDefinition> カード ID => 定義（ID 順）
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $cards = [];
            foreach (self::ROWS as $id => $row) {
                $cards[$id] = new ProjectCardDefinition(
                    $id,
                    $row[0],
                    $row[1],
                    $row[2],
                    ProjectType::from($row[3]),
                    $row[4],
                    $row[5],
                    $row[6],
                    EffectTiming::from($row[7]),
                    EffectKind::from($row[8]),
                    ActiveCondition::from($row[9]),
                    CardTexts::PROJECT[$id]['ja'],
                    CardTexts::PROJECT[$id]['en'],
                );
            }
            self::$cache = $cards;
        }

        return self::$cache;
    }

    public static function get(string $id): ProjectCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("未定義のカード ID: {$id}");
    }

    /**
     * 効果の識別子（`nameId`）からカードを引く。
     */
    public static function byNameId(string $nameId): ProjectCardDefinition
    {
        foreach (self::all() as $card) {
            if ($card->nameId === $nameId) {
                return $card;
            }
        }

        throw new \OutOfBoundsException("未定義の name_id: {$nameId}");
    }
}
