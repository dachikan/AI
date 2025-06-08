<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'AI サービス情報' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .ai-card {
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .ai-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .ai-icon {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 8px;
        }
        .ai-icon-large {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 12px;
        }
        .rating-stars {
            color: #ffc107;
        }
        .badge-free {
            background-color: #28a745;
        }
        .badge-recommended {
            background-color: #ffc107;
            color: #000;
        }
        .badge-paid {
            background-color: #007bff;
        }
        .comparison-table {
            font-size: 0.9rem;
        }
        .comparison-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .ranking-item {
            transition: transform 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .ranking-item:hover {
            transform: translateX(5px);
        }
        .ranking-item.rank-1 { border-left-color: #ffd700; }
        .ranking-item.rank-2 { border-left-color: #c0c0c0; }
        .ranking-item.rank-3 { border-left-color: #cd7f32; }
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="AI_dashboard.php">
                <i class="fas fa-robot"></i> AI Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="AI_dashboard.php">ダッシュボード</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AI_list.php">一覧</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AI_ranking.php">ランキング</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AI_comparison.php">比較</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AI_categories.php">カテゴリ</a>
                    </li>
                </ul>
                <form class="d-flex" action="AI_search.php" method="GET">
                    <input class="form-control me-2" type="search" name="q" placeholder="AIサービスを検索">
                    <button class="btn btn-outline-light" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </nav>
