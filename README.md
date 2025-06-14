# AI情報表示システム

AIサービスの情報を表示・比較するWebアプリケーション

## 機能
- AIサービス一覧表示
- 人気ランキング
- サービス比較
- カテゴリ別表示

## セットアップ
1. データベース設定
2. db_connect.phpの設定
3. Webサーバーの設定

## 使用技術
- PHP
- MySQL
- Bootstrap 5
- JavaScript

# AI情報表示ページ作成用プロンプト 20250607
このMySQL DB中のAIInfoテーブルのデータはincludes/db_connect.phpでアクセスできます。
このデータを次の目的で表示するphpプログラムを作成してください。
・各種比較ページ
・各種一覧ページ
　絞り込み　おすすめ、有料/無料、画像、チャット
　並べ替え 名前、評価、登録日
・その他ページ（提案して下さい）
# 表示形式
　カード形式：アイコン、名称、概要、画像、価格、ランキング、バッジ(無料、おすすめ)などを含む
　すべての情報を表示する詳細表示：「使ったよ」リンク（読者が投稿できるAIを使ってみたと言うnote記事へのリンク）、いいね／だめね
# 使ったよリンク
## 投稿促進機能
noteの新規投稿画面に直接リンク
テンプレート付きで投稿しやすく
タイトルと本文が自動入力される
## テンプレート
AIサービス名と説明
使用目的、感想、評価の項目
適切なハッシュタグ
公式サイトへのリンク
## 二段構え
「記事を書く」: 新規投稿用
「みんなの使用感」: 既存記事検索用
## 追跡機能
投稿履歴をMySQLで管理
投稿カウント表示
フィードバック機能
# 参考画面
　省略
# データ例　登録数:62件 MySQL
INSERT INTO AIInfo (
ai_type_id, ai_service, company_name, ai_icon, brand_color, description,
strengths, limitations, model_name, max_tokens, supported_languages,
input_types, output_types, official_url, launch_url, api_available,
api_url, pricing_model, free_tier_available, registration_required,
is_active, sort_order, popularity_score, version, release_date, last_updated_info
) VALUES
テキスト生成AI (ai_type_id = 1)
(1, 'ChatGPT', 'OpenAI', 'chatgpt-icon.png', '#10A37F',
'OpenAIが開発した対話型AI。自然な会話形式で様々な質問に回答し、文章作成、翻訳、要約、プログラミングなど幅広いタスクに対応。',
'自然な対話、幅広い知識、コード生成、多言語対応、論理的思考',
'2021年9月以降の情報は限定的、リアルタイム情報なし、画像生成は別サービス',
'GPT-4', 128000, '["日本語","英語","中国語","韓国語","フランス語","ドイツ語","スペイン語"]',
'text,image', 'text', 'https://openai.com/chatgpt',
'https://chat.openai.com/?q={prompt}', TRUE, 'https://api.openai.com/v1/chat/completions',
'freemium', TRUE, TRUE, TRUE, 1, 95, 'GPT-4', '2022-11-30', NOW()),
# 注意
・dbアクセス関連の汎用的な関数を作る場合はincludes/db_connect.phpに追加します。
・画面共通部分をヘッダ・フッタで定義、includeに
・画面表示ページは先頭にAI_を付加します。
・アイコンはicons/xxx-icon.pngの形式です
・画像はimagesに入れておきます。

MIT License

Copyright (c) 2024 Your Name

Permission is hereby granted, free of charge, to any person obtaining a copy...