/**
 * AIアイコンの読み込み処理とちらつき対策
 */

class AIIconManager {
  constructor() {
    // 既存のdefault-icon.pngを使用
    this.defaultIcon = "icons/default-icon.png"
    this.loadedIcons = new Set()
    this.failedIcons = new Set()
    this.init()
  }

  init() {
    // ページ読み込み時に全てのアイコンを処理
    document.addEventListener("DOMContentLoaded", () => {
      this.processAllIcons()
    })

    // 動的に追加されたアイコンも処理
    this.observeNewIcons()
  }

  processAllIcons() {
    const icons = document.querySelectorAll('img[src*="icons/"]')
    icons.forEach((icon) => this.processIcon(icon))
  }

  processIcon(img) {
    if (!img || this.loadedIcons.has(img.src)) return

    // ローディング状態を設定
    this.setLoadingState(img)

    // 画像の読み込みを試行
    this.loadImage(img)
      .then(() => {
        this.setLoadedState(img)
        this.loadedIcons.add(img.src)
      })
      .catch(() => {
        this.setErrorState(img)
        this.failedIcons.add(img.src)
      })
  }

  loadImage(img) {
    return new Promise((resolve, reject) => {
      const testImg = new Image()

      testImg.onload = () => {
        img.src = testImg.src
        resolve()
      }

      testImg.onerror = () => {
        reject()
      }

      // タイムアウト設定（3秒）
      setTimeout(() => {
        reject(new Error("Timeout"))
      }, 3000)

      testImg.src = img.src
    })
  }

  setLoadingState(img) {
    img.classList.add("loading")
    img.style.opacity = "0.7"

    // プレースホルダーとして既存のdefault-icon.pngを設定
    if (!img.src.includes("default-icon.png")) {
      const originalSrc = img.src
      img.src = this.defaultIcon
      img.dataset.originalSrc = originalSrc
    }
  }

  setLoadedState(img) {
    img.classList.remove("loading", "error", "default-icon")
    img.classList.add("loaded")
    img.style.opacity = "1"
  }

  setErrorState(img) {
    img.classList.remove("loading")
    img.classList.add("error", "default-icon")
    img.style.opacity = "1"

    // 既存のdefault-icon.pngを設定
    img.src = this.defaultIcon

    // alt属性からサービス名を取得してツールチップに設定
    if (img.alt) {
      img.title = `${img.alt} (デフォルトアイコン)`
    }
  }

  observeNewIcons() {
    // MutationObserverで動的に追加されたアイコンを監視
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            const icons = node.querySelectorAll ? node.querySelectorAll('img[src*="icons/"]') : []
            icons.forEach((icon) => this.processIcon(icon))

            // 追加されたノード自体がアイコンの場合
            if (node.tagName === "IMG" && node.src.includes("icons/")) {
              this.processIcon(node)
            }
          }
        })
      })
    })

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    })
  }

  // 手動でアイコンを再読み込み
  reloadIcon(img) {
    this.loadedIcons.delete(img.src)
    this.failedIcons.delete(img.src)
    this.processIcon(img)
  }

  // 全てのエラーアイコンを再試行
  retryFailedIcons() {
    const errorIcons = document.querySelectorAll("img.error")
    errorIcons.forEach((icon) => {
      this.reloadIcon(icon)
    })
  }
}

// グローバルインスタンスを作成
window.aiIconManager = new AIIconManager()

// ユーティリティ関数
function createAIIcon(src, alt, className = "ai-icon") {
  const img = document.createElement("img")
  img.src = src
  img.alt = alt
  img.className = className
  img.onerror = function () {
    window.aiIconManager.setErrorState(this)
  }

  // 即座に処理
  window.aiIconManager.processIcon(img)

  return img
}

// レイジーローディング対応
function setupLazyLoading() {
  if ("IntersectionObserver" in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const img = entry.target
          if (img.dataset.src) {
            img.src = img.dataset.src
            img.removeAttribute("data-src")
            window.aiIconManager.processIcon(img)
            observer.unobserve(img)
          }
        }
      })
    })

    document.querySelectorAll("img[data-src]").forEach((img) => {
      imageObserver.observe(img)
    })
  }
}

// ページ読み込み完了後にレイジーローディングを設定
document.addEventListener("DOMContentLoaded", setupLazyLoading)
