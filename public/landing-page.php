<?php
declare(strict_types=1);

/**
 * Landing Page pública - Orçamentaria Express / Mecânica
 * Arquivo sugerido: /public/landing-page.php
 */

$planosFallback = [
    [
        'id' => 1,
        'nome' => 'FREE',
        'limite_orcamentos' => 10,
        'limite_produtos' => 20,
        'preco' => 0.00,
        'ativo' => 1,
    ],
    [
        'id' => 2,
        'nome' => 'PRO',
        'limite_orcamentos' => 1000,
        'limite_produtos' => 500,
        'preco' => 89.90,
        'ativo' => 1,
    ],
];

$planos = $planosFallback;

try {
    $dbFile = __DIR__ . '/../config/db.php';

    if (is_file($dbFile)) {
        require_once $dbFile;

        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->query("
                SELECT
                    id,
                    nome,
                    limite_orcamentos,
                    limite_produtos,
                    preco,
                    ativo
                FROM planos
                WHERE ativo = 1
                ORDER BY preco ASC, id ASC
            ");

            $dadosPlanos = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            if (is_array($dadosPlanos) && count($dadosPlanos) > 0) {
                $planos = $dadosPlanos;
            }
        }
    }
} catch (Throwable $e) {
    error_log('[LANDING_ORCAMENTARIA_PLANOS] ' . $e->getMessage());
}

function landing_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function landing_money($value): string
{
    $valor = (float)$value;

    if ($valor <= 0) {
        return 'R$ 0';
    }

    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function landing_limit_label($value, string $singular, string $plural): string
{
    $n = (int)$value;

    if ($n <= 0) {
        return 'Limite sob consulta';
    }

    return number_format($n, 0, ',', '.') . ' ' . ($n === 1 ? $singular : $plural);
}

function landing_plan_tag(string $nome): string
{
    $nome = strtoupper(trim($nome));

    if ($nome === 'FREE') {
        return 'Entrada gratuita';
    }

    if ($nome === 'PRO') {
        return 'Mais volume e escala';
    }

    return 'Plano disponível';
}

function landing_plan_text(string $nome): string
{
    $nome = strtoupper(trim($nome));

    if ($nome === 'FREE') {
        return 'Comece a organizar orçamentos, produtos e aprovações sem custo inicial.';
    }

    if ($nome === 'PRO') {
        return 'Amplie a operação com mais capacidade mensal e catálogo maior.';
    }

    return 'Escolha o plano mais adequado ao momento da sua operação.';
}

function landing_plan_cta(string $nome): array
{
    $nome = strtoupper(trim($nome));

    if ($nome === 'FREE') {
        return [
            'label' => 'Começar no FREE',
            'href' => '/login.php',
            'class' => 'btn btn-primary',
            'target' => '',
        ];
    }

    $numero = '5571993143374';
    $mensagem = 'Olá, Bacuri Digital! Quero conhecer o plano ' . $nome . ' da Orçamentaria Express.';
    $href = 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensagem);

    return [
        'label' => 'Quero o ' . $nome,
        'href' => $href,
        'class' => 'btn btn-whatsapp',
        'target' => ' target="_blank" rel="noopener noreferrer"',
    ];
}

$whatsappNumero = '5571993143374';
$whatsappPrincipal = 'https://wa.me/' . $whatsappNumero . '?text=' . rawurlencode(
    'Olá, Bacuri Digital! Quero conhecer a Orçamentaria Express para minha oficina.'
);
$whatsappPro = 'https://wa.me/' . $whatsappNumero . '?text=' . rawurlencode(
    'Olá, Bacuri Digital! Quero conhecer o plano PRO da Orçamentaria Express.'
);

$siteUrl = 'https://www.mecanica.bacuridigital.com';
$pageUrl = $siteUrl . '/landing-page.php';
$ogImage = $siteUrl . '/imagens/orcamentaria_express.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Orçamentaria Express | Orçamentos profissionais para oficinas mecânicas</title>
    <meta name="description" content="Crie orçamentos em segundos, gere PDFs profissionais, receba aprovação eletrônica e acompanhe a conversão da sua oficina com a Orçamentaria Express.">
    <meta name="theme-color" content="#0f172a">

    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Orçamentaria Express">
    <meta property="og:title" content="Orçamentaria Express | Orçamentos em segundos para oficinas">
    <meta property="og:description" content="PDF profissional, aprovação eletrônica, estatísticas, taxa de conversão e uma base que aprende com cada orçamento aprovado.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= landing_h($pageUrl) ?>">
    <meta property="og:image" content="<?= landing_h($ogImage) ?>">
    <meta property="og:image:secure_url" content="<?= landing_h($ogImage) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="Orçamentaria Express - Sistema de orçamentos para oficinas mecânicas">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Orçamentaria Express | Orçamentos em segundos">
    <meta name="twitter:description" content="Propostas profissionais, aprovação eletrônica e métricas para vender com mais controle.">
    <meta name="twitter:image" content="<?= landing_h($ogImage) ?>">

    <link rel="icon" type="image/png" href="/imagens/orcamentaria_express.png">
    <link rel="shortcut icon" href="/imagens/orcamentaria_express.png">

    <style>
        :root{
            --bg:#020617;
            --bg-soft:#081123;
            --panel:#0f172a;
            --panel-2:#111c32;
            --card:rgba(15,23,42,.84);
            --card-strong:rgba(15,23,42,.96);
            --line:rgba(148,163,184,.18);
            --line-strong:rgba(148,163,184,.32);
            --text:#e5edf8;
            --muted:#a9b5c8;
            --muted-2:#8ea0ba;
            --white:#ffffff;
            --brand:#38bdf8;
            --brand-2:#2563eb;
            --brand-soft:rgba(56,189,248,.16);
            --green:#22c55e;
            --green-soft:rgba(34,197,94,.14);
            --amber:#fbbf24;
            --amber-soft:rgba(251,191,36,.14);
            --purple:#a78bfa;
            --purple-soft:rgba(167,139,250,.14);
            --shadow:0 28px 90px rgba(2,6,23,.44);
            --radius:28px;
            --radius-md:22px;
            --radius-sm:18px;
            --max:1180px;
        }

        *{
            box-sizing:border-box;
        }

        html{
            scroll-behavior:smooth;
        }
        
        main section[id]{
            scroll-margin-top:120px;
        }
        
        @media (max-width: 760px){
            main section[id]{
                scroll-margin-top:155px;
            }
        }

        body{
            margin:0;
            min-height:100vh;
            color:var(--text);
            background:
                radial-gradient(circle at 12% 4%, rgba(56,189,248,.18), transparent 30%),
                radial-gradient(circle at 90% 9%, rgba(37,99,235,.18), transparent 28%),
                radial-gradient(circle at 78% 76%, rgba(167,139,250,.12), transparent 34%),
                linear-gradient(145deg, #020617 0%, #071123 48%, #0b1224 100%);
            font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height:1.5;
        }

        a{
            color:inherit;
        }

        .container{
            width:min(calc(100% - 40px), var(--max));
            margin:0 auto;
        }

        .site-header{
            position:sticky;
            top:0;
            z-index:50;
            border-bottom:1px solid rgba(148,163,184,.12);
            background:rgba(2,6,23,.76);
            backdrop-filter:blur(18px);
        }

        .nav{
            min-height:82px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:22px;
        }

        .brand{
            display:inline-flex;
            align-items:center;
            gap:14px;
            min-width:0;
            text-decoration:none;
        }

        .brand img{
            display:block;
            width:min(240px, 48vw);
            height:auto;
            max-height:110px;
            margin: 5px;
            object-fit:contain;
        }

        .brand-copy{
            display:none;
            flex-direction:column;
            line-height:1.05;
        }

        .brand-copy strong{
            font-size:.98rem;
            letter-spacing:.02em;
        }

        .brand-copy span{
            color:var(--muted);
            font-size:.78rem;
        }

        .nav-links{
            display:flex;
            align-items:center;
            gap:22px;
            margin-left:auto;
        }

        .nav-links a{
            color:var(--muted);
            text-decoration:none;
            font-weight:600;
            font-size:.94rem;
            transition:color .2s ease, transform .2s ease;
        }

        .nav-links a:hover{
            color:var(--white);
            transform:translateY(-1px);
        }

        .nav-actions{
            display:flex;
            align-items:center;
            gap:10px;
        }

        .menu-toggle{
            display:none;
            min-width:46px;
            min-height:46px;
            border:1px solid var(--line-strong);
            border-radius:15px;
            background:rgba(15,23,42,.78);
            color:var(--white);
            font-size:1.25rem;
            font-weight:700;
            cursor:pointer;
        }

        .mobile-panel{
            display:none;
            padding:0 0 18px;
        }

        .mobile-panel.is-open{
            display:block;
        }

        .mobile-card{
            border:1px solid var(--line);
            background:rgba(15,23,42,.92);
            border-radius:20px;
            padding:14px;
            display:grid;
            gap:10px;
            box-shadow:var(--shadow);
        }

        .mobile-card a{
            padding:12px 13px;
            color:var(--text);
            text-decoration:none;
            font-weight:600;
            border-radius:14px;
            background:rgba(148,163,184,.08);
        }

        .btn{
            min-height:50px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:9px;
            padding:13px 18px;
            border-radius:16px;
            border:1px solid transparent;
            text-decoration:none;
            font-weight:700;
            letter-spacing:.01em;
            transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
            cursor:pointer;
        }

        .btn:hover{
            transform:translateY(-2px);
        }

        .btn-sm{
            min-height:46px;
            padding:11px 15px;
            font-size:.95rem;
        }

        .btn-primary{
            color:#071120;
            background:linear-gradient(135deg, #7dd3fc 0%, #38bdf8 48%, #60a5fa 100%);
            box-shadow:0 18px 38px rgba(56,189,248,.22);
        }

        .btn-secondary{
            color:var(--white);
            border-color:var(--line-strong);
            background:rgba(15,23,42,.72);
        }

        .btn-whatsapp{
            color:#052e16;
            background:linear-gradient(135deg, #86efac 0%, #22c55e 100%);
            box-shadow:0 18px 38px rgba(34,197,94,.18);
        }

        .hero{
            padding:20px 0 64px;
        }

        .hero-grid{
            display:grid;
            grid-template-columns:minmax(0, 1.06fr) minmax(390px, .94fr);
            gap:42px;
            align-items:center;
        }

        .eyebrow{
            display:inline-flex;
            align-items:center;
            flex-wrap:wrap;
            gap:9px;
            margin:0 0 18px;
            padding:9px 14px;
            border:1px solid rgba(125,211,252,.26);
            border-radius:999px;
            background:rgba(56,189,248,.10);
            color:#bae6fd;
            font-size:.88rem;
            font-weight:700;
            letter-spacing:.02em;
        }

        .hero h1{
            margin:0;
            max-width:820px;
            color:var(--white);
            font-size:clamp(2.65rem, 5.6vw, 5.2rem);
            line-height:.98;
            letter-spacing:-.055em;
        }

        .hero p{
            max-width:760px;
            margin:22px 0 0;
            color:var(--muted);
            font-size:clamp(1.05rem, 1.8vw, 1.22rem);
            line-height:1.72;
        }

        .hero-actions{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            margin-top:30px;
        }

        .hero-pills{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-top:28px;
        }

        .mini-pill{
            min-height:43px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:9px 13px;
            border:1px solid var(--line);
            border-radius:999px;
            background:rgba(15,23,42,.68);
            color:#dbe7f7;
            font-weight:600;
            font-size:.92rem;
        }

        .hero-showcase{
            position:relative;
        }

        .hero-showcase::before{
            content:"";
            position:absolute;
            inset:-24px;
            z-index:-1;
            border-radius:38px;
            background:
                radial-gradient(circle at 32% 24%, rgba(56,189,248,.22), transparent 34%),
                radial-gradient(circle at 72% 72%, rgba(34,197,94,.16), transparent 34%);
            filter:blur(8px);
        }

        .mock-panel{
            overflow:hidden;
            border:1px solid var(--line-strong);
            border-radius:34px;
            background:linear-gradient(180deg, rgba(15,23,42,.96), rgba(8,17,35,.98));
            box-shadow:var(--shadow);
        }

        .mock-top{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:20px 20px 16px;
            border-bottom:1px solid var(--line);
        }

        .mock-top strong{
            display:block;
            color:var(--white);
            font-size:1rem;
        }

        .mock-top span{
            display:block;
            color:var(--muted);
            font-size:.88rem;
        }

        .live-tag{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 11px;
            border-radius:999px;
            background:var(--green-soft);
            color:#bbf7d0;
            font-weight:700;
            font-size:.82rem;
            white-space:nowrap;
        }

        .live-tag::before{
            content:"";
            width:8px;
            height:8px;
            border-radius:999px;
            background:var(--green);
            box-shadow:0 0 0 7px rgba(34,197,94,.12);
        }

        .mock-body{
            padding:20px;
        }

        .metrics{
            display:grid;
            grid-template-columns:repeat(3, minmax(0,1fr));
            gap:12px;
        }

        .metric{
            min-height:114px;
            padding:15px;
            border:1px solid var(--line);
            border-radius:20px;
            background:rgba(148,163,184,.07);
        }

        .metric span{
            display:block;
            color:var(--muted);
            font-size:.79rem;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:.05em;
        }

        .metric strong{
            display:block;
            margin-top:10px;
            color:var(--white);
            font-size:1.42rem;
            line-height:1.1;
            letter-spacing:-.03em;
        }

        .metric small{
            display:block;
            margin-top:8px;
            color:#cbd5e1;
            font-size:.79rem;
        }

        .timeline{
            display:grid;
            gap:12px;
            margin-top:16px;
        }

        .timeline-item{
            display:grid;
            grid-template-columns:46px minmax(0,1fr) auto;
            gap:12px;
            align-items:center;
            padding:14px;
            border:1px solid var(--line);
            border-radius:20px;
            background:rgba(15,23,42,.84);
        }

        .timeline-icon{
            width:46px;
            height:46px;
            display:grid;
            place-items:center;
            border-radius:15px;
            font-size:1.24rem;
            background:var(--brand-soft);
        }

        .timeline-item strong{
            display:block;
            color:var(--white);
            font-size:.98rem;
        }

        .timeline-item span{
            display:block;
            margin-top:3px;
            color:var(--muted);
            font-size:.87rem;
        }

        .status{
            padding:8px 10px;
            border-radius:999px;
            font-size:.78rem;
            font-weight:700;
            white-space:nowrap;
            border:1px solid transparent;
        }

        .status-blue{
            color:#bae6fd;
            background:rgba(56,189,248,.13);
            border-color:rgba(56,189,248,.24);
        }

        .status-green{
            color:#bbf7d0;
            background:rgba(34,197,94,.13);
            border-color:rgba(34,197,94,.24);
        }

        .status-purple{
            color:#ddd6fe;
            background:rgba(167,139,250,.13);
            border-color:rgba(167,139,250,.24);
        }

        .section{
            padding:76px 0;
        }

        .section-soft{
            position:relative;
        }

        .section-soft::before{
            content:"";
            position:absolute;
            inset:0;
            z-index:-1;
            background:linear-gradient(180deg, rgba(15,23,42,.18), rgba(15,23,42,.52), rgba(15,23,42,.18));
        }

        .section-head{
            max-width:820px;
            margin-bottom:30px;
        }

        .section-kicker{
            margin:0 0 10px;
            color:#93c5fd;
            font-weight:700;
            font-size:.9rem;
            text-transform:uppercase;
            letter-spacing:.1em;
        }

        .section h2{
            margin:0;
            color:var(--white);
            font-size:clamp(2rem, 4vw, 3.4rem);
            line-height:1.08;
            letter-spacing:-.045em;
        }

        .section-head p{
            margin:16px 0 0;
            color:var(--muted);
            font-size:1.08rem;
            line-height:1.72;
        }

        .cards{
            display:grid;
            grid-template-columns:repeat(4, minmax(0,1fr));
            gap:16px;
        }

        .feature-card,
        .audience-card,
        .plan-card,
        .flow-card,
        .value-card{
            border:1px solid var(--line);
            border-radius:var(--radius-md);
            background:var(--card);
            box-shadow:0 20px 60px rgba(2,6,23,.24);
        }

        .feature-card{
            min-height:270px;
            padding:22px;
        }

        .feature-icon{
            width:54px;
            height:54px;
            display:grid;
            place-items:center;
            margin-bottom:18px;
            border-radius:18px;
            background:rgba(56,189,248,.14);
            color:#e0f2fe;
            font-size:1.5rem;
        }

        .feature-card:nth-child(2) .feature-icon,
        .feature-card:nth-child(6) .feature-icon{
            background:var(--green-soft);
        }

        .feature-card:nth-child(3) .feature-icon,
        .feature-card:nth-child(7) .feature-icon{
            background:var(--amber-soft);
        }

        .feature-card:nth-child(4) .feature-icon,
        .feature-card:nth-child(8) .feature-icon{
            background:var(--purple-soft);
        }

        .feature-card h3,
        .audience-card h3,
        .flow-card h3,
        .value-card h3,
        .plan-card h3{
            margin:0;
            color:var(--white);
            font-size:1.17rem;
            line-height:1.28;
            letter-spacing:-.02em;
        }

        .feature-card p,
        .audience-card p,
        .flow-card p,
        .value-card p,
        .plan-card p{
            margin:12px 0 0;
            color:var(--muted);
            line-height:1.66;
            font-size:.98rem;
        }

        .split{
            display:grid;
            grid-template-columns:minmax(0, .9fr) minmax(0, 1.1fr);
            gap:28px;
            align-items:start;
        }

        .benefit-list{
            display:grid;
            gap:12px;
            margin-top:24px;
        }

        .benefit{
            display:flex;
            gap:12px;
            align-items:flex-start;
            padding:15px 16px;
            border:1px solid var(--line);
            border-radius:18px;
            background:rgba(15,23,42,.72);
        }

        .benefit i{
            flex:0 0 auto;
            width:27px;
            height:27px;
            display:grid;
            place-items:center;
            border-radius:999px;
            color:#052e16;
            background:#86efac;
            font-style:normal;
            font-weight:800;
            font-size:.9rem;
        }

        .benefit strong{
            display:block;
            color:var(--white);
            font-size:1rem;
        }

        .benefit span{
            display:block;
            margin-top:3px;
            color:var(--muted);
            font-size:.95rem;
        }

        .value-grid{
            display:grid;
            grid-template-columns:repeat(2, minmax(0,1fr));
            gap:16px;
        }

        .value-card{
            min-height:205px;
            padding:22px;
        }

        .value-number{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:46px;
            height:46px;
            margin-bottom:18px;
            border-radius:16px;
            color:#082f49;
            background:#7dd3fc;
            font-weight:800;
        }

        .flow-grid{
            display:grid;
            grid-template-columns:repeat(4, minmax(0,1fr));
            gap:16px;
        }

        .flow-card{
            position:relative;
            min-height:248px;
            padding:22px;
        }

        .flow-card::after{
            content:"";
            position:absolute;
            top:42px;
            right:-9px;
            width:18px;
            height:2px;
            background:rgba(125,211,252,.42);
        }

        .flow-card:last-child::after{
            display:none;
        }

        .flow-step{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:44px;
            height:44px;
            margin-bottom:17px;
            border-radius:15px;
            background:rgba(56,189,248,.14);
            color:#bae6fd;
            font-weight:800;
        }

        .plans-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(285px, 1fr));
            gap:18px;
        }

        .plan-card{
            position:relative;
            overflow:hidden;
            padding:24px;
        }

        .plan-card.is-pro{
            border-color:rgba(125,211,252,.42);
            background:
                radial-gradient(circle at top right, rgba(56,189,248,.18), transparent 34%),
                var(--card-strong);
        }

        .plan-tag{
            display:inline-flex;
            align-items:center;
            gap:8px;
            margin-bottom:18px;
            padding:8px 11px;
            border-radius:999px;
            color:#dbeafe;
            background:rgba(59,130,246,.16);
            border:1px solid rgba(147,197,253,.20);
            font-size:.82rem;
            font-weight:700;
        }

        .plan-price{
            display:flex;
            align-items:flex-end;
            gap:8px;
            margin-top:18px;
            color:var(--white);
        }

        .plan-price strong{
            font-size:clamp(2.1rem, 4vw, 3rem);
            letter-spacing:-.05em;
            line-height:1;
        }

        .plan-price span{
            padding-bottom:5px;
            color:var(--muted);
            font-weight:600;
        }

        .plan-list{
            display:grid;
            gap:11px;
            margin:22px 0 24px;
            padding:0;
            list-style:none;
        }

        .plan-list li{
            display:flex;
            gap:11px;
            align-items:flex-start;
            color:#dbe7f7;
            font-weight:600;
        }

        .plan-list li::before{
            content:"✓";
            flex:0 0 auto;
            width:22px;
            height:22px;
            display:grid;
            place-items:center;
            margin-top:1px;
            border-radius:999px;
            color:#052e16;
            background:#86efac;
            font-size:.78rem;
            font-weight:800;
        }

        .plan-actions{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
        }

        .audience-grid{
            display:grid;
            grid-template-columns:repeat(3, minmax(0,1fr));
            gap:16px;
        }

        .audience-card{
            min-height:220px;
            padding:22px;
        }

        .audience-label{
            display:inline-flex;
            align-items:center;
            margin-bottom:16px;
            padding:7px 10px;
            border-radius:999px;
            background:rgba(148,163,184,.10);
            color:#cbd5e1;
            font-weight:700;
            font-size:.82rem;
        }

        .cta{
            padding:76px 0 92px;
        }

        .cta-box{
            position:relative;
            overflow:hidden;
            display:grid;
            grid-template-columns:minmax(0, 1fr) auto;
            gap:28px;
            align-items:center;
            padding:34px;
            border:1px solid rgba(125,211,252,.30);
            border-radius:34px;
            background:
                radial-gradient(circle at 86% 20%, rgba(125,211,252,.18), transparent 34%),
                radial-gradient(circle at 10% 90%, rgba(34,197,94,.12), transparent 30%),
                rgba(15,23,42,.94);
            box-shadow:var(--shadow);
        }

        .cta-box h2{
            margin:0;
            color:var(--white);
            font-size:clamp(2rem, 4vw, 3.4rem);
            line-height:1.08;
            letter-spacing:-.045em;
        }

        .cta-box p{
            max-width:760px;
            margin:16px 0 0;
            color:var(--muted);
            font-size:1.08rem;
            line-height:1.72;
        }

        .cta-actions{
            display:flex;
            flex-wrap:wrap;
            justify-content:flex-end;
            gap:12px;
        }

        .footer{
            border-top:1px solid rgba(148,163,184,.14);
            padding:28px 0 42px;
        }

        .footer-inner{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            color:var(--muted);
            font-size:.95rem;
        }

        .footer strong{
            color:var(--white);
        }

        .footer a{
            color:#bae6fd;
            text-decoration:none;
            font-weight:700;
        }

        @media (max-width: 1120px){
            .nav-links{
                display:none;
            }

            .menu-toggle{
                display:inline-flex;
                align-items:center;
                justify-content:center;
            }

            .hero-grid,
            .split,
            .cta-box{
                grid-template-columns:1fr;
            }

            .cards{
                grid-template-columns:repeat(2, minmax(0,1fr));
            }

            .flow-grid{
                grid-template-columns:repeat(2, minmax(0,1fr));
            }

            .flow-card:nth-child(2)::after{
                display:none;
            }

            .cta-actions{
                justify-content:flex-start;
            }
        }

        @media (max-width: 760px){
            .container{
                width:min(calc(100% - 28px), var(--max));
            }

            .nav{
                min-height:76px;
                gap:12px;
            }

            .brand img{
                width:min(200px, 58vw);
                max-height:90px;
                margin-left:50%;
                margin-top:25px;
                margin-bottom:10px;
            }

            .nav-actions .btn{
                display:none;
            }

            .hero{
                padding:10px 0 54px;
            }

            .hero-grid{
                gap:28px;
            }

            .hero h1{
                font-size:clamp(2.35rem, 12vw, 3.85rem);
            }

            .hero-actions,
            .plan-actions,
            .cta-actions{
                display:grid;
                grid-template-columns:1fr;
            }

            .hero-actions .btn,
            .plan-actions .btn,
            .cta-actions .btn{
                width:100%;
            }

            .mock-top{
                align-items:flex-start;
                flex-direction:column;
            }

            .metrics,
            .cards,
            .value-grid,
            .flow-grid,
            .audience-grid{
                grid-template-columns:1fr;
            }

            .timeline-item{
                grid-template-columns:46px minmax(0,1fr);
            }

            .timeline-item .status{
                grid-column:1 / -1;
                justify-self:start;
                margin-left:58px;
            }

            .feature-card,
            .flow-card,
            .audience-card{
                min-height:0;
            }

            .flow-card::after{
                display:none;
            }

            .section{
                padding:58px 0;
            }

            .cta{
                padding:58px 0 72px;
            }

            .cta-box{
                padding:24px;
                border-radius:28px;
            }

            .footer-inner{
                flex-direction:column;
                align-items:flex-start;
            }
        }
        
        img {
          animation: pulsar 5s infinite;
        }
        
        @keyframes pulsar {
          0% {
            transform: scale(1);
          }
          80% {
            transform: scale(1);
          }
          90% {
            transform: scale(0.95);
          }
          100% {
            transform: scale(1);
          }
        }
        
        
        
        
        

                /* CTA de acesso ao sistema: destaque fixo + brilho ocasional em tons de azul */
        .btn-access-glow {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            color: #071120 !important;
            border-color: rgba(125, 211, 252, 0.98) !important;
            background: linear-gradient(135deg, #7dd3fc 0%, #38bdf8 48%, #60a5fa 100%) !important;
            box-shadow:
                0 16px 36px rgba(56, 189, 248, 0.34),
                0 0 0 1px rgba(186, 230, 253, 0.30) inset;
            animation: acessoSistemaDestaque 6.5s ease-in-out infinite !important;
        }

        .btn-access-glow::before {
            content: '';
            position: absolute;
            top: -60%;
            left: -48%;
            width: 34%;
            height: 220%;
            z-index: 0;
            pointer-events: none;
            opacity: 0;
            transform: rotate(24deg) translateX(0);
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(240, 249, 255, 0.94) 48%,
                rgba(255, 255, 255, 0) 100%
            );
            animation: acessoSistemaBrilhoPassando 6.5s ease-in-out infinite !important;
        }

        .btn-access-glow::after {
            content: '';
            position: absolute;
            inset: -4px;
            z-index: -1;
            border-radius: inherit;
            border: 1px solid rgba(125, 211, 252, 0.78);
            opacity: 0;
            pointer-events: none;
            animation: acessoSistemaHalo 6.5s ease-out infinite !important;
        }

        .btn-access-glow:hover {
            transform: translateY(-3px);
            color: #071120 !important;
            border-color: rgba(186, 230, 253, 1) !important;
            background: linear-gradient(135deg, #bae6fd 0%, #38bdf8 48%, #3b82f6 100%) !important;
            box-shadow:
                0 20px 50px rgba(56, 189, 248, 0.54),
                0 0 0 1px rgba(224, 242, 254, 0.42) inset;
        }
        
        @keyframes acessoSistemaDestaque {
            0%, 61%, 100% {
                box-shadow:
                    0 16px 36px rgba(56, 189, 248, 0.34),
                    0 0 0 1px rgba(186, 230, 253, 0.30) inset;
                transform: translateY(0) scale(1);
            }

            68% {
                box-shadow:
                    0 22px 56px rgba(56, 189, 248, 0.64),
                    0 0 0 7px rgba(56, 189, 248, 0.13),
                    0 0 0 1px rgba(224, 242, 254, 0.48) inset;
                transform: translateY(-1px) scale(1.025);
            }

            76% {
                box-shadow:
                    0 18px 46px rgba(96, 165, 250, 0.48),
                    0 0 0 3px rgba(125, 211, 252, 0.09),
                    0 0 0 1px rgba(186, 230, 253, 0.36) inset;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes acessoSistemaBrilhoPassando {
            0%, 60% {
                opacity: 0;
                transform: rotate(0deg) translateX(0);
            }

            64% {
                opacity: 1;
            }

            76% {
                opacity: 1;
                transform: rotate(0deg) translateX(520%);
            }

            80%, 100% {
                opacity: 0;
                transform: rotate(0deg) translateX(520%);
            }
        }

        @keyframes acessoSistemaHalo {
            0%, 61%, 100% {
                opacity: 0;
                transform: scale(0.96);
            }

            67% {
                opacity: 0.90;
                transform: scale(1);
            }

            79% {
                opacity: 0;
                transform: scale(1.14);
            }
        }
        img {
          animation: pulsar 5s infinite;
        }
        
        @keyframes pulsar {
          0% {
            transform: scale(1);
          }
          80% {
            transform: scale(1);
          }
          90% {
            transform: scale(0.95);
          }
          100% {
            transform: scale(1);
          }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <nav class="nav" aria-label="Navegação principal">
            <a class="brand" href="#inicio" aria-label="Orçamentaria Express">
                <img src="/imagens/orcamentaria_express.png" alt="Orçamentaria Express">
                <span class="brand-copy">
                    <strong>Orçamentaria Express</strong>
                    <span>Mecânica multitenant</span>
                </span>
            </a>

            <div class="nav-links">
                <a href="#funcionalidades">Funcionalidades</a>
                <a href="#diferenciais">Diferenciais</a>
                <a href="#fluxo">Como funciona</a>
                <a href="#planos">Planos</a>
                <a href="#para-quem">Para quem é</a>
            </div>

            <div class="nav-actions">
                <a class="btn btn-secondary btn-sm" href="/login.php" style="display:none">Entrar</a>
                <a class="btn btn-primary btn-access-glow btn-sm" href="/login.php" style="margin-left:150px">Começar grátis</a>
                <button class="menu-toggle" type="button" id="menuToggle" aria-label="Abrir menu" aria-expanded="false">☰</button>
            </div>
        </nav>

        <div class="mobile-panel" id="mobilePanel">
            <div class="mobile-card">
                <a href="#funcionalidades">Funcionalidades</a>
                <a href="#diferenciais">Diferenciais</a>
                <a href="#fluxo">Como funciona</a>
                <a href="#planos">Planos</a>
                <a href="#para-quem">Para quem é</a>
                <a href="/login.php">Entrar no sistema</a>
            </div>
        </div>
    </div>
</header>

<main>
    <section class="hero" id="inicio">
        <div class="container hero-grid">
            <div>
                <div class="eyebrow">Plano FREE disponível • Orçamento em segundos • Aprovação eletrônica</div>
                <h1>Orçamentos profissionais que ajudam sua oficina a vender com mais velocidade.</h1>
                <p>
                    A Orçamentaria Express organiza o fluxo comercial da oficina: você monta propostas em poucos passos,
                    gera PDFs profissionais, envia links para aprovação e acompanha o que realmente converte.
                    Conforme os orçamentos são aprovados, a base fica mais inteligente e reduz o retrabalho do próximo atendimento.
                </p>

                <div class="hero-actions">
                    <a class="btn btn-primary btn-access-glow" href="/login.php">Começar no FREE</a>
                    <a class="btn btn-secondary" href="#planos">Ver planos</a>
                    <a class="btn btn-whatsapp" href="<?= landing_h($whatsappPrincipal) ?>" target="_blank" rel="noopener noreferrer">Falar com a Bacuri</a>
                </div>

                <div class="hero-pills" aria-label="Principais benefícios">
                    <span class="mini-pill">⚡ Orçamento em segundos</span>
                    <span class="mini-pill">📄 PDF profissional</span>
                    <span class="mini-pill">✅ Aprovação online</span>
                    <span class="mini-pill">📈 Taxa de conversão</span>
                </div>
            </div>

            <div class="hero-showcase" aria-label="Painel ilustrativo da Orçamentaria Express">
                <div class="mock-panel">
                    <div class="mock-top">
                        <div>
                            <strong>Painel comercial da oficina</strong>
                            <span>Exemplo ilustrativo do fluxo da Orçamentaria</span>
                        </div>
                        <div class="live-tag">Operação ativa</div>
                    </div>

                    <div class="mock-body">
                        <div class="metrics">
                            <div class="metric">
                                <span>Aprovados</span>
                                <strong>42</strong>
                                <small>Orçamentos fechados</small>
                            </div>
                            <div class="metric">
                                <span>Conversão</span>
                                <strong>68%</strong>
                                <small>Decisões concluídas</small>
                            </div>
                            <div class="metric">
                                <span>Proposto</span>
                                <strong>R$ 38k</strong>
                                <small>Valor em análise</small>
                            </div>
                        </div>

                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-icon">🧾</div>
                                <div>
                                    <strong>Orçamento #248 enviado</strong>
                                    <span>PDF e link público prontos para o cliente.</span>
                                </div>
                                <span class="status status-blue">Enviado</span>
                            </div>

                            <div class="timeline-item">
                                <div class="timeline-icon">👁️</div>
                                <div>
                                    <strong>Cliente visualizou a proposta</strong>
                                    <span>Status atualizado para acompanhar o funil.</span>
                                </div>
                                <span class="status status-purple">Visualizado</span>
                            </div>

                            <div class="timeline-item">
                                <div class="timeline-icon">✅</div>
                                <div>
                                    <strong>Aprovação confirmada</strong>
                                    <span>Cliente e produtos podem entrar automaticamente na base.</span>
                                </div>
                                <span class="status status-green">Aprovado</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-soft" id="funcionalidades">
        <div class="container">
            <div class="section-head">
                <p class="section-kicker">Funcionalidades</p>
                <h2>Uma plataforma pensada para transformar orçamento em processo comercial.</h2>
                <p>
                    A Orçamentaria Express cobre desde a montagem da proposta até a aprovação final,
                    mantendo dados, histórico e indicadores em um ambiente multitenant.
                </p>
            </div>

            <div class="cards">
                <article class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Orçamento em segundos</h3>
                    <p>Fluxo guiado para selecionar cliente, itens e valores com menos cliques e mais rapidez no atendimento.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">🧠</div>
                    <h3>O sistema aprende com você</h3>
                    <p>Itens e clientes reaproveitáveis reduzem digitação. Após aprovações, a base pode evoluir automaticamente.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">📄</div>
                    <h3>PDFs profissionais</h3>
                    <p>Propostas padronizadas, prontas para apresentar ao cliente com aparência comercial mais forte.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">✅</div>
                    <h3>Aprovação eletrônica</h3>
                    <p>O cliente acessa o link público, visualiza a proposta e aprova ou recusa sem depender de login.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Estatísticas de orçamento</h3>
                    <p>Acompanhe aprovados, recusados, valores propostos, faturamento aprovado e evolução do mês.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Taxa de conversão</h3>
                    <p>Veja quanto do que foi apresentado realmente virou aprovação e tome decisões com mais precisão.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3>Preço flexível no orçamento</h3>
                    <p>Mude o valor de um serviço no orçamento sem precisar alterar o preço padrão do produto cadastrado.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">🏢</div>
                    <h3>Estrutura multitenant</h3>
                    <p>Cada empresa opera com sua própria conta, dados isolados, limites por plano e crescimento controlado.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="diferenciais">
        <div class="container split">
            <div>
                <div class="section-head">
                    <p class="section-kicker">Diferenciais</p>
                    <h2>Menos retrabalho. Mais controle sobre o que vende.</h2>
                    <p>
                        O sistema foi desenhado para oficinas que precisam ganhar tempo no balcão,
                        profissionalizar a proposta e entender a própria performance comercial.
                    </p>
                </div>

                <div class="benefit-list">
                    <div class="benefit">
                        <i>✓</i>
                        <div>
                            <strong>Valor do serviço ajustável caso a caso</strong>
                            <span>Preserve um preço base no catálogo e negocie no orçamento sem bagunçar o cadastro.</span>
                        </div>
                    </div>

                    <div class="benefit">
                        <i>✓</i>
                        <div>
                            <strong>Histórico preservado</strong>
                            <span>Os itens do orçamento ficam registrados com nome e preço de cada proposta.</span>
                        </div>
                    </div>

                    <div class="benefit">
                        <i>✓</i>
                        <div>
                            <strong>Fluxo público por token</strong>
                            <span>O cliente aprova ou recusa a proposta a partir de um link exclusivo.</span>
                        </div>
                    </div>

                    <div class="benefit">
                        <i>✓</i>
                        <div>
                            <strong>Indicadores no dashboard</strong>
                            <span>Tenha leitura clara de faturamento aprovado, total proposto e conversão.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="value-grid">
                <article class="value-card">
                    <span class="value-number">01</span>
                    <h3>Centralização</h3>
                    <p>Clientes, produtos, orçamentos, PDFs e decisões do cliente em um único fluxo.</p>
                </article>

                <article class="value-card">
                    <span class="value-number">02</span>
                    <h3>Agilidade</h3>
                    <p>Criação guiada e reaproveitamento de informações para atender mais rápido.</p>
                </article>

                <article class="value-card">
                    <span class="value-number">03</span>
                    <h3>Conversão</h3>
                    <p>Status, aprovação eletrônica e métricas para enxergar o resultado das propostas.</p>
                </article>

                <article class="value-card">
                    <span class="value-number">04</span>
                    <h3>Escala SaaS</h3>
                    <p>Planos FREE e PRO, contas separadas e estrutura pronta para múltiplas empresas.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-soft" id="fluxo">
        <div class="container">
            <div class="section-head">
                <p class="section-kicker">Como funciona</p>
                <h2>Do atendimento à aprovação, sem perder o fio da venda.</h2>
                <p>
                    A proposta nasce rápido, chega ao cliente com apresentação profissional e volta para o painel
                    com informação suficiente para acompanhar o resultado.
                </p>
            </div>

            <div class="flow-grid">
                <article class="flow-card">
                    <span class="flow-step">1</span>
                    <h3>Monte o orçamento</h3>
                    <p>Escolha o cliente, adicione serviços e ajuste preços quando necessário.</p>
                </article>

                <article class="flow-card">
                    <span class="flow-step">2</span>
                    <h3>Gere PDF e envie</h3>
                    <p>Compartilhe uma proposta profissional e um link de visualização para o cliente.</p>
                </article>

                <article class="flow-card">
                    <span class="flow-step">3</span>
                    <h3>Receba a decisão</h3>
                    <p>O cliente aprova ou recusa eletronicamente, e o status é registrado no sistema.</p>
                </article>

                <article class="flow-card">
                    <span class="flow-step">4</span>
                    <h3>Aprenda e acompanhe</h3>
                    <p>A base é enriquecida e o dashboard mostra aprovações, valores e conversão.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="planos">
        <div class="container">
            <div class="section-head">
                <p class="section-kicker">Planos</p>
                <h2>Comece no FREE e evolua para o PRO quando sua operação crescer.</h2>
                <p>
                    A landing lê os planos ativos do banco atual. Assim, a vitrine comercial acompanha os limites configurados no sistema.
                </p>
            </div>

            <div class="plans-grid">
                <?php foreach ($planos as $plano): ?>
                    <?php
                        $nomePlano = strtoupper(trim((string)($plano['nome'] ?? 'Plano')));
                        $precoPlano = (float)($plano['preco'] ?? 0);
                        $ctaPlano = landing_plan_cta($nomePlano);
                        $classePlano = $nomePlano === 'PRO' ? 'plan-card is-pro' : 'plan-card';
                    ?>
                    <article class="<?= landing_h($classePlano) ?>">
                        <span class="plan-tag"><?= landing_h(landing_plan_tag($nomePlano)) ?></span>
                        <h3>Plano <?= landing_h($nomePlano) ?></h3>
                        <p><?= landing_h(landing_plan_text($nomePlano)) ?></p>

                        <div class="plan-price">
                            <strong><?= landing_h(landing_money($precoPlano)) ?></strong>
                            <span>/mês</span>
                        </div>

                        <ul class="plan-list">
                            <li><?= landing_h(landing_limit_label($plano['limite_orcamentos'] ?? 0, 'orçamento por mês', 'orçamentos por mês')) ?></li>
                            <li><?= landing_h(landing_limit_label($plano['limite_produtos'] ?? 0, 'produto ativo', 'produtos ativos')) ?></li>
                            <li>PDF profissional e proposta pública</li>
                            <li>Métricas, conversão e gestão multitenant</li>
                        </ul>

                        <div class="plan-actions">
                            <a class="<?= landing_h($ctaPlano['class']) ?> btn-access-glow" href="<?= landing_h($ctaPlano['href']) ?>"<?= $ctaPlano['target'] ?>>
                                <?= landing_h($ctaPlano['label']) ?>
                            </a>

                            <?php if ($nomePlano === 'PRO'): ?>
                                <a class="btn btn-secondary" href="<?= landing_h($whatsappPro) ?>" target="_blank" rel="noopener noreferrer">
                                    Falar com a Bacuri
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section-soft" id="para-quem">
        <div class="container">
            <div class="section-head">
                <p class="section-kicker">Para quem é</p>
                <h2>Feita para negócios que precisam orçar rápido e fechar com mais clareza.</h2>
                <p>
                    A Orçamentaria Express atende operações que precisam apresentar serviços com profissionalismo,
                    organizar a negociação e medir o que vira venda.
                </p>
            </div>

            <div class="audience-grid">
                <article class="audience-card">
                    <span class="audience-label">Oficinas</span>
                    <h3>Mecânicas com alto volume de cotações</h3>
                    <p>Agilidade na criação, histórico claro e aprovação eletrônica para acelerar o atendimento.</p>
                </article>

                <article class="audience-card">
                    <span class="audience-label">Centros automotivos</span>
                    <h3>Serviços, peças e propostas organizadas</h3>
                    <p>Catálogo reaproveitável, preços ajustáveis e PDFs profissionais para melhorar a percepção.</p>
                </article>

                <article class="audience-card">
                    <span class="audience-label">Prestadores especializados</span>
                    <h3>Mais controle da proposta ao fechamento</h3>
                    <p>Acompanhe o funil de orçamentos e entenda a taxa de conversão do seu processo comercial.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="cta-box">
                <div>
                    <h2>Transforme orçamento em um processo mais rápido, profissional e mensurável.</h2>
                    <p>
                        Entre pelo plano FREE, conheça o fluxo na prática e migre para o PRO quando precisar de mais volume,
                        mais produtos e mais escala para sua operação.
                    </p>
                </div>

                <div class="cta-actions">
                    <a class="btn btn-primary btn-access-glow" href="/login.php">Começar grátis</a>
                    <a class="btn btn-whatsapp" href="<?= landing_h($whatsappPrincipal) ?>" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="container footer-inner">
        <div>
            <strong>Orçamentaria Express</strong> — Orçamentos profissionais, aprovação eletrônica e métricas para oficinas.
        </div>
        <div>© <?= date('Y') ?> Bacuri Digital. Todos os direitos reservados.</div>
    </div>
</footer>

<script>
(function(){
    const toggle = document.getElementById('menuToggle');
    const panel = document.getElementById('mobilePanel');

    if (!toggle || !panel) {
        return;
    }

    function fecharMenu(){
        panel.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = '☰';
    }

    toggle.addEventListener('click', function(){
        const aberto = panel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        toggle.textContent = aberto ? '×' : '☰';
    });

    panel.querySelectorAll('a').forEach(function(link){
        link.addEventListener('click', fecharMenu);
    });

    window.addEventListener('resize', function(){
        if (window.innerWidth > 1120) {
            fecharMenu();
        }
    });
})();
</script>
</body>
</html>
