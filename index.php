<?php
// エラー表示を有効化（開発時のみ）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// リファラーチェック関数
function isAllowedReferrer() {
    // PHP実行環境がCLIかどうかをチェック（セキュリティ追加）
    if (php_sapi_name() === 'cli') {
	return false;
    }

    // サーバー変数の存在と直接アクセスの確認を厳密に行う
    $isDirectAccess = (
	!isset($_SERVER['HTTP_REFERER']) || 
	empty($_SERVER['HTTP_REFERER'])
    );

    // 直接アクセスの場合は即座にfalseを返す
    if ($isDirectAccess) {
	return false;
    }
    
    // リファラーのURLをパース
    $referrer = parse_url($_SERVER['HTTP_REFERER']);
    
    // ホスト名がない場合はfalseを返す
    if (!isset($referrer['host'])) {
	return false;
    }
    
    // 許可されたホスト名のパターンを定義
    $allowedPatterns = [
	'nsk.org' => ['exact' => true],
	'www.nsk.org' => ['exact' => true],
	'note.com' => [
	    'exact' => false, 
	    'path' => '/gifted_panda752/'
	]
    ];
    
    // ホストごとのチェック
    foreach ($allowedPatterns as $allowedHost => $config) {
	// ホスト名のチェック
	$hostMatches = (
	    $referrer['host'] === $allowedHost || 
	    ($config['exact'] === false && strpos($referrer['host'], $allowedHost) !== false)
	);
	
	// パスのチェック
	$pathMatches = true;
	if (isset($config['path'])) {
	    $path = $referrer['path'] ?? '';
	    $pathMatches = (strpos($path, $config['path']) === 0);
	}
	
	// ホストとパスの両方が一致すれば許可
	if ($hostMatches && $pathMatches) {
	    return true;
	}
    }
    
    return false;
}

// リファラーをチェック
$accessAllowed = isAllowedReferrer();

// データベース接続変数の初期化
$db_connected = false;
$categories = [];

// アクセス許可されていない場合は、即座にアクセス制限ページを表示
if (!$accessAllowed) {
    // データベース接続や追加情報の取得を行わない
    header("HTTP/1.1 403 Forbidden");
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アクセス制限 - AI活用サンプル集</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
	body {
	    font-family: Arial, sans-serif;
	    margin: 0;
	    padding: 0;
	    background: linear-gradient(135deg, #f3f4f6, #d5d7dc);
	    display: flex;
	    justify-content: center;
	    align-items: center;
	    height: 100vh;
	}
	.container {
	    text-align: center;
	    background: #fff;
	    padding: 30px;
	    border-radius: 15px;
	    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
	    animation: fadeIn 1s ease-in-out;
	    max-width: 600px;
	}
	@keyframes fadeIn {
	    from { opacity: 0; }
	    to { opacity: 1; }
	}
	h1 {
	    color: #e74c3c;
	    font-size: 24px;
	}
	p {
	    margin: 20px 0;
	    font-size: 18px;
	    color: #555;
	}
	a {
	    display: inline-block;
	    margin: 20px auto;
	    padding: 10px 20px;
	    background-color: #3498db;
	    color: #fff;
	    text-decoration: none;
	    border-radius: 5px;
	    font-weight: bold;
	    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
	}
	a:hover {
	    background-color: #2c81ba;
	}
	footer {
	    margin-top: 20px;
	    color: #aaa;
	    font-size: 14px;
	}
    </style>
</head>
<body>
    <div class="container">
	<h1>アクセスが制限されています</h1>
	<p>このページは、指定されたリンク経路からのみ閲覧可能です。</p>
	<p>以下のnote記事から正しいリンクでアクセスしてください。</p>
	<a href="https://note.com/gifted_panda752/n/nf465c53c87d9" target="_blank">老人とＡＩ：多種類のＡＩ探求記 ー 同じプロンプトで描いてみた</a>
	<hr/>
	<small class="error-code">エラーコード: REF-001</small>
    </div>
    <?php 
	// デバッグ情報を追加
	if (isset($_SERVER['HTTP_REFERER'])) {
	    echo '<div class="debug-info">';
	    echo '<p>デバッグ情報:</p>';
	    echo '<p>リファラー: ' . htmlspecialchars($_SERVER['HTTP_REFERER']) . '</p>';
	    $referrer = parse_url($_SERVER['HTTP_REFERER']);
	    echo '<p>解析結果:</p>';
	    echo '<pre>' . print_r($referrer, true) . '</pre>';
	    echo '</div>';
	}
    ?>
</body>
</html>
<?php
    exit(); // 処理を完全に終了
}

// アクセス許可がある場合のみデータベース接続
try {
    $servername = "mysql213.phy.lolipop.lan";
    $username = "LAA1337491";
    $password = "kami2004";
    $dbname = "LAA1337491-nsk";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$conn->connect_error) {
	$db_connected = true;
	$conn->set_charset("utf8mb4");
	
	// カテゴリ情報のみ取得（ナビゲーション用）
	//$sql = "SELECT CategoryID, CategoryName FROM Categories";
	$sql = "SELECT id, name FROM AIPromptCategories";
	$result = $conn->query($sql);
	
	if ($result) {
	    while($row = $result->fetch_assoc()) {
		$categories[] = $row;
	    }
	}
	
	$conn->close();
    }
} catch (Exception $e) {
    // エラーは無視し、静的コンテンツを表示
    header("HTTP/1.1 403 Forbidden");
    exit();
}
?>

<?php
// エラー表示を有効化（開発時のみ）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// データベース接続（最小限の情報取得のみ）
$db_connected = false;
$categories = [];
try {
    $servername = "mysql213.phy.lolipop.lan";
    $username = "LAA1337491";
    $password = "kami2004";
    $dbname = "LAA1337491-nsk";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$conn->connect_error) {
	$db_connected = true;
	$conn->set_charset("utf8mb4");
	
	// カテゴリ情報のみ取得（ナビゲーション用）
	//$sql = "SELECT CategoryID, CategoryName FROM Categories";
	$sql = "SELECT id, name FROM AIPromptCategories";
	$result = $conn->query($sql);
	
	if ($result) {
	    while($row = $result->fetch_assoc()) {
		$categories[] = $row;
	    }
	}
	
	$conn->close();
    }
} catch (Exception $e) {
    // エラーは無視し、静的コンテンツを表示
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI活用サンプル集 - 初心者から上級者まで使えるAIプロンプト集</title>
    <meta name="description" content="noteで紹介した画像生成テクニックをさらに発展させた、様々なAIサービスで使える実用的なプロンプト集。画像生成からビジネス文書作成まで幅広く対応。">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
	/* カスタムスタイル */
	body {
	    font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
	}
	.hero-section {
	    background: linear-gradient(135deg, #2a2a72 0%, #009ffd 100%);
	    color: white;
	    padding: 3rem 0;
	    margin-bottom: 2rem;
	}
	.article-preview {
	    border: 1px solid #dee2e6;
	    border-radius: 10px;
	    padding: 15px;
	    margin-bottom: 20px;
	}
	.image-gallery {
	    display: flex;
	    flex-wrap: wrap;
	    gap: 10px;
	    margin: 20px 0;
	}
	.image-gallery img {
	    border-radius: 5px;
	    object-fit: cover;
	    max-height: 200px;
	    width: auto;
	}
	.feature-card {
	    border-radius: 10px;
	    transition: transform 0.3s ease;
	    height: 100%;
	    margin-bottom: 20px;
	}
	.feature-card:hover {
	    transform: translateY(-5px);
	    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
	}
	.price-tag {
	    background-color: #28a745;
	    color: white;
	    padding: 5px 10px;
	    border-radius: 20px;
	    font-weight: bold;
	    display: inline-block;
	}
	.cta-section {
	    background-color: #f8f9fa;
	    padding: 3rem 0;
	    margin: 2rem 0;
	    border-radius: 10px;
	}
	.prompt-example {
	    background-color: #f8f9fa;
	    padding: 15px;
	    border-radius: 5px;
	    font-family: monospace;
	    margin: 15px 0;
	}
    </style>
</head>
<body>
    <header>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
	    <div class="container">
		<a class="navbar-brand" href="#">AI活用サンプル集</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
		    <span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarNav">
		    <ul class="navbar-nav ms-auto">
			<li class="nav-item"><a class="nav-link" href="#article-preview">記事の内容</a></li>
			<li class="nav-item"><a class="nav-link" href="#ai-examples">AI画像例</a></li>
			<li class="nav-item"><a class="nav-link" href="#features">特徴</a></li>
			<li class="nav-item"><a class="nav-link" href="#categories">カテゴリ</a></li>
			<li class="nav-item"><a class="nav-link" href="#faq">よくある質問</a></li>
		    </ul>
		</div>
	    </div>
	</nav>
    </header>

    <main>
	<!-- ヒーローセクション -->
	<section class="hero-section">
	    <div class="container text-center">
		<h1 class="display-4 mb-4">ＡＩプロンプトを試して、<br>
					    あなたの創作を次のレベルへ</h1>
		<p class="lead mb-4">noteで紹介したプロンプトとＡＩサービスを使える実用的なページ公開中</p>
		<div class="d-flex justify-content-center gap-3 mb-4">
		    <img src="images/gemini-icon.png" alt="Gemini" height="40" class="mx-2">
		    <img src="images/gpt4-icon.png" alt="GPT-4" height="40" class="mx-2">
		    <img src="images/midjourney-icon.png" alt="Midjourney" height="40" class="mx-2">
		    <img src="images/stable-diffusion-logo.png" alt="Stable Diffusion" height="40" class="mx-2">
		</div>
		<a href="AI-list.php" class="btn btn-light btn-lg">サンプル一覧を見る</a>
		<p class="mt-2 text-light">現在、<span class="price-tag">このサイトは構築中です。</span>その点をご理解の上、ご利用下さい</p>
	    </div>
	</section>

	<!-- 記事プレビューセクション -->
	<section id="article-preview" class="container mb-5">
	    <div class="row align-items-center">
		<div class="col-md-6">
		    <h2>noteの記事からさらに深く</h2>
		    <p class="lead">note記事のご紹介は、ＡＩの可能性のほんの一部です。</p>
		    <div class="article-preview">
			<h5>記事で紹介したプロンプト例：</h5>
			<div class="prompt-example">
			    中世の老賢者、長い白髪と白いひげ、フード付きの暗いローブ、神秘的な雰囲気、暗い背景、ドラマチックな照明、超写実的、詳細な質感
			</div>
			<p>このプロンプトを使って、記事では様々な老賢者の画像を生成しました。このサイトでは、さらに多くのバリエーションと応用例を提供しています。</p>
		    </div>
		</div>
		<div class="col-md-6">
		    <div class="image-gallery">
			<!-- 記事の画像を表示 -->
			<img src="images/wizard1.jpg.jpg" alt="AIで生成した老賢者の画像1" class="img-fluid rounded shadow">
			<img src="images/wizard2.jpg.jpg" alt="AIで生成した老賢者の画像2" class="img-fluid rounded shadow">
			<img src="images/wizard3.jpg.jpg" alt="AIで生成した老賢者の画像3" class="img-fluid rounded shadow">
			<img src="images/wizard4.jpg.jpg" alt="AIで生成した老賢者の画像4" class="img-fluid rounded shadow">
		    </div>
		    <p class="text-center text-muted">記事で紹介したAI生成画像の例</p>
		</div>
	    </div>
	</section>

	<!-- AI画像例セクション -->
	<section id="ai-examples" class="container mb-5">
	    <h2 class="text-center mb-4">様々なAIで生成した画像例</h2>
	    <div class="row">
	    <div class="col-md-3 mb-4">
		<div class="card h-100">
		    <img src="images/chatgpt-example.jpg.jpg" class="card-img-top" alt="ChatGPTで生成したテキスト">
		    <div class="card-body">
			<h5 class="card-title">ChatGPT</h5>
			<p class="card-text">自然な対話と多様なテキスト生成が得意</p>
		    </div>
		</div>
	    </div>
	    <div class="col-md-3 mb-4">
		<div class="card h-100">
		    <img src="images/gemini-example.jpg.jpg" class="card-img-top" alt="Google Geminiで生成したコンテンツ">
		    <div class="card-body">
			<h5 class="card-title">Google Gemini</h5>
			<p class="card-text">マルチモーダルな情報処理と理解が可能</p>
		    </div>
		</div>
	    </div>
		<div class="col-md-3 mb-4">
		    <div class="card h-100">
			<img src="images/stable-diffusion-example.jpg.jpg" class="card-img-top" alt="Stable Diffusionで生成した画像">
			<div class="card-body">
			    <h5 class="card-title">Stable Diffusion</h5>
			    <p class="card-text">カスタマイズ性が高く、細かい調整が可能</p>
			</div>
		    </div>
		</div>
		<div class="col-md-3 mb-4">
		    <div class="card h-100">
			<img src="images/leonardo-example.jpg.jpg" class="card-img-top" alt="Leonardo.AIで生成した画像">
			<div class="card-body">
			    <h5 class="card-title">Leonardo.AI</h5>
			    <p class="card-text">高品質なテクスチャと詳細な表現が特徴</p>
			</div>
		    </div>
		</div>
	    </div>
	</section>

	<!-- 特徴セクション -->
	<section id="features" class="container mb-5">
	    <h2 class="text-center mb-4">AI活用サンプル集の特徴</h2>
	    <div class="row g-4">
		<div class="col-md-4">
		    <div class="card feature-card h-100">
			<div class="card-body text-center">
			    <div class="fs-1 mb-3">🔍</div>
			    <h3 class="card-title">実用的なプロンプト</h3>
			    <p class="card-text">日常生活やビジネスですぐに使える実用的なプロンプトを多数収録。コピペですぐに使えます。</p>
			</div>
		    </div>
		</div>
		<div class="col-md-4">
		    <div class="card feature-card h-100">
			<div class="card-body text-center">
			    <div class="fs-1 mb-3">🖼️</div>
			    <h3 class="card-title">入出力例付き</h3>
			    <p class="card-text">各プロンプトには実際の入力例と出力例を掲載。期待される結果がすぐにわかります。</p>
			</div>
		    </div>
		</div>
		<div class="col-md-4">
		    <div class="card feature-card h-100">
			<div class="card-body text-center">
			    <div class="fs-1 mb-3">🤖</div>
			    <h3 class="card-title">複数AIに対応</h3>
			    <p class="card-text">ChatGPT、Gemini、Midjourney、Stable Diffusionなど、様々なAIサービスに対応したプロンプトを提供。</p>
			</div>
		    </div>
		</div>
	    </div>
	</section>

	<!-- カテゴリセクション -->
	<section id="categories" class="container mb-5">
	    <h2 class="text-center mb-4">プロンプトカテゴリ</h2>
	    <div class="row g-4">
		<?php if (!empty($categories)): ?>
		    <?php 
		    $icons = [
			'文書作成' => '📝',
			'画像生成' => '🖼️',
			'会計管理' => '📊',
			'イベント企画' => '🎪',
			'情報発信' => '📢',
			'防災対策' => '🚨',
			'コミュニティ活性化' => '👥',
			'家庭内' => '🏠',
			'その他' => '🔍'
		    ];
		    ?>
		    <?php foreach ($categories as $category): ?>
			<div class="col-md-3 mb-3">
			    <div class="card feature-card h-100">
				<div class="card-body text-center">
				    <div class="fs-1 mb-3">
					<?php 
					$icon = '📋';
					if (isset($icons[$category['name']])) {
					    $icon = $icons[$category['name']];
					}
					echo $icon;
					?>
				    </div>
				    <h3 class="card-title h5"><?php echo htmlspecialchars($category['name']); ?></h3>
				    <a href="AISampleList_with_advanced.php?category=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-secondary mt-2">このカテゴリを見る</a>
				</div>
			    </div>
			</div>
		    <?php endforeach; ?>
		<?php else: ?>
		    <!-- データベース接続がない場合は静的なカテゴリを表示 -->
		    <div class="col-md-3 mb-3">
			<div class="card feature-card h-100">
			    <div class="card-body text-center">
				<div class="fs-1 mb-3">📝</div>
				<h3 class="card-title h5">文書作成</h3>
				<a href="AISampleList_with_advanced.php?category=1" class="btn btn-sm btn-outline-secondary mt-2">このカテゴリを見る</a>
			    </div>
			</div>
		    </div>
		    <div class="col-md-3 mb-3">
			<div class="card feature-card h-100">
			    <div class="card-body text-center">
				<div class="fs-1 mb-3">🖼️</div>
				<h3 class="card-title h5">画像生成</h3>
				<a href="AISampleList_with_advanced.php?category=2" class="btn btn-sm btn-outline-secondary mt-2">このカテゴリを見る</a>
			    </div>
			</div>
		    </div>
		    <div class="col-md-3 mb-3">
			<div class="card feature-card h-100">
			    <div class="card-body text-center">
				<div class="fs-1 mb-3">📊</div>
				<h3 class="card-title h5">会計管理</h3>
				<a href="AISampleList_with_advanced.php?category=3" class="btn btn-sm btn-outline-secondary mt-2">このカテゴリを見る</a>
			    </div>
			</div>
		    </div>
		    <div class="col-md-3 mb-3">
			<div class="card feature-card h-100">
			    <div class="card-body text-center">
				<div class="fs-1 mb-3">🎪</div>
				<h3 class="card-title h5">イベント企画</h3>
				<a href="AISampleList_with_advanced.php?category=4" class="btn btn-sm btn-outline-secondary mt-2">このカテゴリを見る</a>
			    </div>
			</div>
		    </div>
		<?php endif; ?>
	    </div>
	</section>

	<!-- CTA セクション -->
	<section class="cta-section">
	    <div class="container text-center">
		<h2 class="mb-4">今すぐAIプロンプトを試してみませんか？</h2>
		<p class="lead mb-4">使ってみることであなたのAI活用スキルが大きく向上します</p>
		<a href="AISample-AllTry.php" class="btn btn-success btn-lg">サンプル一覧を見る</a>
		<p class="mt-3 text-muted">※ すべてのサンプルに無制限にアクセスできます</p>
	    </div>
	</section>

	<!-- FAQ セクション -->
	<section id="faq" class="container mb-5">
	    <h2 class="text-center mb-4">よくある質問</h2>
	    <div class="accordion" id="faqAccordion">
		<div class="accordion-item">
		    <h2 class="accordion-header">
			<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
			    どのようなAIサービスに対応していますか？
			</button>
		    </h2>
		    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
			<div class="accordion-body">
			    ChatGPT（GPT-3.5/GPT-4）<br>
			    Google Gemini<br>
			    Midjourney<br>
			    Stable Diffusion<br>
			    Leonardo.AI<br>
			    Adobe Fireflyなど<br>
			    主要なAIサービスに対応したプロンプトを提供しています。各サンプルページには、対応するAIサービスが明記されています。
			</div>
		    </div>
		</div>
		<div class="accordion-item">
		    <h2 class="accordion-header">
			<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
			    使ってみることで何が得られますか？
			</button>
		    </h2>
		    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
			<div class="accordion-body">
			    すべてのＡＩプロンプトサンプルにアクセスできます。入力例と出力例、詳細な解説、応用のコツなど、実用的な情報がすべて閲覧可能になります。将来追加されるサンプルも含めて、すべてのコンテンツにアクセスできます。<br>
			    ＡＩを使ってみて実体験した後で詳細な解説を読めば、理解が速くなります。
			</div>
		    </div>
		</div>
		<div class="accordion-item">
		    <h2 class="accordion-header">
			<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
			    noteの記事で紹介されていた画像生成のプロンプトも含まれていますか？
			</button>
		    </h2>
		    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
			<div class="accordion-body">
			    はい、noteの記事「老人向けAI活用ガイド」で紹介した画像生成プロンプトも含まれています。さらに、それらを発展させたバリエーションや、異なるAIエンジンでの結果比較なども提供しています。記事で気に入ったプロンプトがあれば、このサイトでさらに多くのアイデアを見つけることができます。
			</div>
		    </div>
		</div>
		<div class="accordion-item">
		    <h2 class="accordion-header">
			<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
			    AIサービス自体の利用料は含まれていますか？
			</button>
		    </h2>
		    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
			<div class="accordion-body">
			    いいえ。紹介しているAIサービス自体の利用には、各サービスの利用規約に従った別途料金が発生する場合があります。ただし、多くのAIサービスは無料プランや無料トライアルを提供しているので、まずはそれらを活用することをお勧めします。
			</div>
		    </div>
		</div>
		<!-- index.phpのFAQセクションに追加 -->
		<div class="accordion-item">
		    <h2 class="accordion-header">
			<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
			    AIサービスの利用制限や料金はどうなっていますか？
			</button>
		    </h2>
		    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
			<div class="accordion-body">
			    <p>各AIサービスには、それぞれ異なる利用制限や料金体系があります：</p>
			    <ul>
				<li><strong>Meta AI</strong>: 現在、日本からは利用できません。</li>
				<li><strong>Midjourney</strong>: 料金プランの選択が必須です。無料トライアルはありません。</li>
				<li><strong>Pika</strong>: 料金プランの選択が必須です。</li>
				<li><strong>ChatGPT</strong>: 基本機能は無料で利用可能ですが、GPT-4などの高度な機能はPlus会員（月額$20）が必要です。</li>
				<li><strong>Google Gemini</strong>: 基本機能は無料で利用可能ですが、高度な機能はGemini Advanced（月額$19.99）が必要です。</li>
			    </ul>
			    <p>各サンプルページでは、そのAIサービスの利用条件についても記載していますので、ご確認ください。</p>
			</div>
		    </div>
		</div>
	    </div>
	</section>
    </main>

    <footer class="bg-dark text-white py-4">
	<div class="container">
	    <div class="row">
		<div class="col-md-6">
		    <h5>AI活用サンプル集</h5>
		    <p>様々なAIサービスで使える実用的なプロンプト集</p>
		</div>
		<div class="col-md-3">
		    <h5>リンク</h5>
		    <ul class="list-unstyled">
			<li><a href="#" class="text-white">ホーム</a></li>
			<li><a href="AISampleList_with_advanced.php" class="text-white">サンプル一覧</a></li>
			<li><a href="#categories" class="text-white">カテゴリ</a></li>
			<li><a href="#faq" class="text-white">よくある質問</a></li>
		    </ul>
		</div>
		<div class="col-md-3">
		    <h5>運営者情報</h5>
		    <ul class="list-unstyled">
			<li><a href="#" class="text-white">運営者情報</a></li>
			<li><a href="#" class="text-white">プライバシーポリシー</a></li>
			<li><a href="#" class="text-white">お問い合わせ</a></li>
		    </ul>
		</div>
	    </div>
	    <hr>
	    <div class="text-center">
		<p>&copy; 2023 AI活用サンプル集. All rights reserved.</p>
	    </div>
	</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>