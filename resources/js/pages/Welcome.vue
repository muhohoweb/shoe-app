<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { ShoppingCart, X, Plus, Minus, ArrowRight, Phone, MapPin, User, Truck, Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next'

// ─── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
  products?: any[]
  categories?: any[]
}>()

const page = usePage()

// ─── Flash / Order Success ──────────────────────────────────────────────────
interface OrderSuccessData {
  uuid: string
  amount: number
  stk_sent: boolean
  stk_message: string
  checkout_request_id: string | null
}

const orderSuccess = ref<OrderSuccessData | null>(null)
const paymentStatus = ref<'pending' | 'completed' | 'failed' | null>(null)
const mpesaReceipt = ref<string | null>(null)
const isPolling = ref(false)

onMounted(() => {
  const flash = (page.props as any).flash
  if (flash?.orderSuccess) {
    orderSuccess.value = flash.orderSuccess
  }
})

// Watch for order success and start polling
watch(orderSuccess, (value) => {
  if (value?.stk_sent && value?.checkout_request_id) {
    paymentStatus.value = 'pending'
    pollPaymentStatus(value.checkout_request_id)
  }
}, { immediate: true })

// ─── Payment Status Polling ─────────────────────────────────────────────────
const pollPaymentStatus = (checkoutRequestId: string) => {
  isPolling.value = true
  let attempts = 0
  const maxAttempts = 40 // 2 minutes (40 * 3 seconds)

  const interval = setInterval(async () => {
    attempts++

    try {
      const res = await fetch(`/api/mpesa/status/${checkoutRequestId}`)
      const data = await res.json()

      if (data.status === 'completed') {
        clearInterval(interval)
        isPolling.value = false
        paymentStatus.value = 'completed'
        mpesaReceipt.value = data.mpesa_receipt
      } else if (data.status === 'failed') {
        clearInterval(interval)
        isPolling.value = false
        paymentStatus.value = 'failed'
      }
    } catch (e) {
      console.error('Poll error', e)
    }

    if (attempts >= maxAttempts) {
      clearInterval(interval)
      isPolling.value = false
    }
  }, 3000)
}

// ─── State ──────────────────────────────────────────────────────────────────
const activeCategory = ref<string | number>('all')
const selectedProduct = ref<any>(null)
const isCartOpen = ref(false)
const isCheckoutOpen = ref(false)
const isSuccessOpen = ref(false)

// ─── Cart ───────────────────────────────────────────────────────────────────
interface CartItem {
  product: any
  size: string
  color: string
  quantity: number
}

const cart = ref<CartItem[]>([])

const cartTotal = computed(() =>
    cart.value.reduce((sum, item) => sum + item.product.price * item.quantity, 0)
)

const cartCount = computed(() =>
    cart.value.reduce((sum, item) => sum + item.quantity, 0)
)

// ─── Filtered Products ──────────────────────────────────────────────────────
const filteredProducts = computed(() => {
  if (!props.products) return []
  if (activeCategory.value === 'all') return props.products
  return props.products.filter((p: any) => p.category_id === activeCategory.value)
})

// ─── Product Detail State ───────────────────────────────────────────────────
const selectedSize = ref<string>('')
const selectedColor = ref<string>('')
const detailQuantity = ref(1)

// ─── Image Gallery State ────────────────────────────────────────────────────
const currentImageIndex = ref(0)
const isZoomed = ref(false)
const mouseX = ref(0)
const mouseY = ref(0)
const isHovering = ref(false)
const zoomLensSize = 150
const zoomFactor = 2

const productImages = computed(() => {
  if (!selectedProduct.value?.images?.length) return []
  return selectedProduct.value.images.map((img: any) => {
    const path = img.path
    return path.startsWith('uploads/') ? '/' + path : '/' + path.replace('products/', 'uploads/')
  })
})

const currentImage = computed(() => {
  return productImages.value[currentImageIndex.value] || null
})

const hasMultipleImages = computed(() => {
  return productImages.value.length > 1
})

// Zoom styles
const mainImageStyle = computed(() => {
  if (!isZoomed.value || !isHovering.value) return {}

  const x = (mouseX.value / 100) * -50
  const y = (mouseY.value / 100) * -50

  return {
    transform: `scale(${zoomFactor}) translate(${x}px, ${y}px)`,
    transition: 'transform 0.1s ease-out',
    cursor: 'zoom-in'
  }
})

const zoomLensStyle = computed(() => {
  if (!isZoomed.value || !isHovering.value) return { display: 'none' }

  return {
    position: 'absolute',
    width: `${zoomLensSize}px`,
    height: `${zoomLensSize}px`,
    left: `${mouseX.value}%`,
    top: `${mouseY.value}%`,
    transform: 'translate(-50%, -50%)',
    backgroundColor: 'rgba(255, 255, 255, 0.3)',
    border: '2px solid #fff',
    borderRadius: '50%',
    pointerEvents: 'none',
    boxShadow: '0 0 10px rgba(0,0,0,0.2)',
    zIndex: 10
  }
})

const handleImageMouseMove = (e: MouseEvent) => {
  if (!isZoomed.value || !isHovering.value) return

  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100

  // Constrain values to prevent edge issues
  mouseX.value = Math.min(100, Math.max(0, x))
  mouseY.value = Math.min(100, Math.max(0, y))
}

const handleImageMouseEnter = () => {
  isHovering.value = true
}

const handleImageMouseLeave = () => {
  isHovering.value = false
  mouseX.value = 0
  mouseY.value = 0
}

const toggleZoom = () => {
  isZoomed.value = !isZoomed.value
  if (!isZoomed.value) {
    mouseX.value = 0
    mouseY.value = 0
    isHovering.value = false
  }
}

const prevImage = () => {
  if (currentImageIndex.value > 0) {
    currentImageIndex.value--
    resetZoom()
  }
}

const nextImage = () => {
  if (currentImageIndex.value < productImages.value.length - 1) {
    currentImageIndex.value++
    resetZoom()
  }
}

const selectImage = (index: number) => {
  currentImageIndex.value = index
  resetZoom()
}

const resetZoom = () => {
  isZoomed.value = false
  isHovering.value = false
  mouseX.value = 0
  mouseY.value = 0
}

const openProductDetail = (product: any) => {
  selectedProduct.value = product
  selectedSize.value = product.sizes?.[0] || ''
  selectedColor.value = product.colors?.[0] || ''
  detailQuantity.value = 1
  currentImageIndex.value = 0
  resetZoom()
}

const closeProductDetail = () => {
  selectedProduct.value = null
  currentImageIndex.value = 0
  resetZoom()
}

// Reset when product changes
watch(selectedProduct, () => {
  currentImageIndex.value = 0
  resetZoom()
})

// ─── Add to Cart ────────────────────────────────────────────────────────────
const addToCart = () => {
  if (!selectedProduct.value || !selectedSize.value || !selectedColor.value) return

  const existing = cart.value.find(
      (item) =>
          item.product.id === selectedProduct.value.id &&
          item.size === selectedSize.value &&
          item.color === selectedColor.value
  )

  if (existing) {
    existing.quantity += detailQuantity.value
  } else {
    cart.value.push({
      product: selectedProduct.value,
      size: selectedSize.value,
      color: selectedColor.value,
      quantity: detailQuantity.value,
    })
  }

  closeProductDetail()
}

const removeFromCart = (index: number) => {
  cart.value.splice(index, 1)
}

const updateQuantity = (index: number, delta: number) => {
  const newQty = cart.value[index].quantity + delta
  if (newQty < 1) {
    removeFromCart(index)
  } else {
    cart.value[index].quantity = newQty
  }
}

// ─── Checkout Form ──────────────────────────────────────────────────────────
const orderForm = useForm({
  customer_name: '',
  mpesa_number: '',
  town: '',
  description: '',
  items: [] as any[],
})

const openCheckout = () => {
  isCartOpen.value = false
  isCheckoutOpen.value = true
}

const submitOrder = () => {
  orderForm.items = cart.value.map((item) => ({
    product_id: item.product.id,
    size: item.size,
    color: item.color,
    quantity: item.quantity,
    price: item.product.price,
  }))

  orderForm.post('/shop/order', {
    preserveScroll: true,
    onSuccess: () => {
      isCheckoutOpen.value = false
      isSuccessOpen.value = true
      cart.value = []
      orderForm.reset()
    },
    onError: () => {
      // errors shown inline
    },
  })
}

// ─── Close Success Modal ────────────────────────────────────────────────────
const closeSuccessModal = () => {
  isSuccessOpen.value = false
  orderSuccess.value = null
  paymentStatus.value = null
  mpesaReceipt.value = null
}

// ─── Parallax Effect ────────────────────────────────────────────────────────
const scrollY = ref(0)

const handleScroll = () => {
  scrollY.value = window.scrollY
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  if (orderSuccess.value) {
    isSuccessOpen.value = true
  }
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})

const parallaxBgStyle = computed(() => ({
  transform: `translateY(${scrollY.value * 0.5}px) scale(${1 + scrollY.value * 0.0005})`,
}))

const heroContentStyle = computed(() => ({
  transform: `translateY(${scrollY.value * -0.2}px)`,
  opacity: Math.max(0, 1 - scrollY.value / 600),
}))

const heroOverlayStyle = computed(() => ({
  opacity: Math.min(0.8, 0.6 + scrollY.value / 1000),
}))

// ─── Helpers ────────────────────────────────────────────────────────────────
const formatPrice = (price: number) => `KES ${Number(price).toLocaleString()}`

const getProductImage = (product: any) => {
  if (product.images && product.images.length > 0) {
    const path = product.images[0].path;
    if (path.startsWith('uploads/')) {
      return '/' + path;
    }
    return '/' + path.replace('products/', 'uploads/');
  }
  return null
}
</script>

<template>
  <Head title="Shop" />

  <!-- ─── PAGE ──────────────────────────────────────────────────────────── -->
  <div style="min-height: 100vh; background: var(--cream);">

    <!-- NAV -->
    <nav style="position: fixed; top: 0; left: 0; right: 0; z-index: 40; background: rgba(245,240,235,0.92); backdrop-filter: blur(8px); border-bottom: 1px solid #e0d9d0;">
      <div style="max-width: 1200px; margin: 0 auto; padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between;">
        <h1 class="font-display" style="font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; color: var(--charcoal);">
          <span style="color: var(--accent);">D</span>rmorch
        </h1>
        <button @click="isCartOpen = true" style="position: relative; background: none; border: none; cursor: pointer; padding: 8px; color: var(--charcoal);">
          <ShoppingCart :size="22" />
          <span v-if="cartCount > 0" style="position: absolute; top: 0; right: 0; background: var(--charcoal); color: white; font-size: 0.6rem; font-weight: 700; width: 17px; height: 17px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--cream);">
            {{ cartCount > 9 ? '9+' : cartCount }}
          </span>
        </button>
      </div>
    </nav>

    <!-- HERO WITH PARALLAX -->
    <section class="hero-parallax" style="position: relative; overflow: hidden; height: 500px; margin-top: 64px;">
      <!-- Parallax Background -->
      <div
          class="hero-bg"
          :style="parallaxBgStyle"
          style="position: absolute; inset: -50px; background: url('/images/auth-bg.jpg') center/cover no-repeat; will-change: transform;"
      ></div>

      <!-- Dynamic Overlay -->
      <div
          :style="heroOverlayStyle"
          style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(26,26,26,0.7) 0%, rgba(26,26,26,0.4) 100%); will-change: opacity;"
      ></div>

      <!-- Floating Decorative Elements -->
      <div
          class="floating-shape shape-1"
          :style="{ transform: `translate(${scrollY * 0.1}px, ${scrollY * -0.15}px)` }"
      ></div>
      <div
          class="floating-shape shape-2"
          :style="{ transform: `translate(${scrollY * -0.08}px, ${scrollY * 0.1}px)` }"
      ></div>

      <!-- Hero Content -->
      <div
          :style="heroContentStyle"
          style="position: relative; z-index: 1; max-width: 1200px; margin: 0 auto; padding: 0 24px; width: 100%; height: 100%; display: flex; align-items: center; will-change: transform, opacity;"
      >
        <div>
          <p style="font-size: 0.7rem; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent); margin-bottom: 16px;">New Collection 2026</p>
          <h2 class="font-display" style="font-size: clamp(2.4rem, 5vw, 4rem); font-weight: 700; color: white; line-height: 1.1; max-width: 560px;">
            Walk with<br><span style="color: var(--accent);">confidence.</span>
          </h2>
          <p style="margin-top: 20px; color: rgba(255,255,255,0.75); font-size: 0.95rem; max-width: 420px; line-height: 1.7;">
            Premium footwear crafted for those who value style, comfort and every step they take.
          </p>
          <button class="btn-primary" style="margin-top: 32px;" @click="document.getElementById('products-section')?.scrollIntoView({ behavior: 'smooth' })">
            Shop Now <ArrowRight :size="16" />
          </button>
        </div>
      </div>

      <!-- Bottom Accent Line -->
      <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--accent), transparent 70%); z-index: 2;"></div>

      <!-- Scroll Indicator -->
      <div class="scroll-indicator" :style="{ opacity: Math.max(0, 1 - scrollY / 150) }">
        <div class="scroll-arrow"></div>
      </div>
    </section>

    <!-- CATEGORY FILTER -->
    <section style="max-width: 1200px; margin: 0 auto; padding: 40px 24px 0;">
      <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <span
            class="tag-pill"
            :class="{ active: activeCategory === 'all' }"
            @click="activeCategory = 'all'"
        >All</span>
        <span
            v-for="cat in categories"
            :key="cat.id"
            class="tag-pill"
            :class="{ active: activeCategory === cat.id }"
            @click="activeCategory = cat.id"
        >
          {{ cat.name }}
          <span v-if="cat.products_count" style="opacity:0.5; margin-left: 4px;">({{ cat.products_count }})</span>
        </span>
      </div>
    </section>

    <!-- PRODUCT GRID -->
    <section id="products-section" style="max-width: 1200px; margin: 0 auto; padding: 32px 24px 80px;">
      <div v-if="filteredProducts.length === 0" style="text-align: center; padding: 60px 0; color: var(--text-muted);">
        No products in this category yet.
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px;">
        <article
            v-for="product in filteredProducts"
            :key="product.id"
            @click="openProductDetail(product)"
            class="product-card"
        >
          <div style="aspect-ratio: 4/3; background: var(--cream-dark); position: relative; overflow: hidden;">
            <img
                v-if="getProductImage(product)"
                :src="getProductImage(product)"
                :alt="product.name"
                class="product-img"
            />
            <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <ShoppingCart :size="32" style="color: #c5bfb5;" />
            </div>
            <span style="position: absolute; top: 12px; left: 12px; background: rgba(255,255,255,0.9); backdrop-filter: blur(4px); padding: 4px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--charcoal-soft);">
              {{ product.category?.name }}
            </span>
          </div>
          <div style="padding: 16px;">
            <h3 style="font-size: 0.92rem; font-weight: 600; color: var(--charcoal); margin: 0 0 6px;">{{ product.name }}</h3>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <span style="font-size: 1rem; font-weight: 700; color: var(--charcoal);">{{ formatPrice(product.price) }}</span>
              <span style="font-size: 0.7rem; color: var(--text-muted);">
                {{ (product.colors || []).length }} colors · {{ (product.sizes || []).length }} sizes
              </span>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- WHATSAPP FLOATING BUTTON -->
    <a href="https://wa.me/254700801438?text=Hi%20DR.Morch%20Crafts%2C%20I%27d%20like%20to%20inquire%20about%20your%20shoes."
       target="_blank"
       rel="noopener noreferrer"
       class="whatsapp-fab"
       title="Chat with us on WhatsApp"
    >
      <svg viewBox="0 0 24 24" width="28" height="28" fill="white">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
    </a>

    <!-- FOOTER -->
    <footer style="background: var(--charcoal); color: rgba(255,255,255,0.5); padding: 40px 24px; text-align: center;">
      <p class="font-display" style="font-size: 1.1rem; color: white; margin: 0 0 8px;">
        <span style="color: var(--accent);">D</span>rmorch
      </p>
      <p style="font-size: 0.85rem; color: rgba(255,255,255,0.7); margin: 0 0 12px; font-weight: 500;">
        DR.MORCH CRAFTS
      </p>
      <p style="font-size: 0.72rem; color: rgba(255,255,255,0.5); margin: 0 0 8px; max-width: 400px; margin-left: auto; margin-right: auto; line-height: 1.5;">
        Ground Room, 3 Ampel House, Ground Floor, DR.Morch Workshop,<br>
        Lurambi-Sichirai Street off Kakamega Webuye Road, Kakamega
      </p>
      <p style="font-size: 0.72rem; color: rgba(255,255,255,0.6); margin: 0 0 8px;">
        Tel: 0700 801 438
      </p>
      <p style="font-size: 0.7rem; color: rgba(255,255,255,0.4); margin: 0 0 4px;">
        Business Reg: BN-RRS3LV6P
      </p>
      <p style="font-size: 0.72rem; margin: 0;">© 2026 DR.Morch Crafts. All rights reserved.</p>
    </footer>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- PRODUCT DETAIL MODAL WITH IMAGE GALLERY                                  -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="selectedProduct" class="modal-overlay" @click.self="closeProductDetail">
    <div class="product-modal product-modal-wide">
      <!-- Close Button -->
      <button @click="closeProductDetail" class="modal-close-btn">
        <X :size="18" />
      </button>

      <!-- Two Column Layout -->
      <div class="product-modal-grid">
        <!-- Left Column - Image Gallery -->
        <div class="product-modal-gallery">
          <!-- Main Image with Zoom -->
          <div class="gallery-main-container">
            <div
                class="gallery-main-image-wrapper"
                @mouseenter="handleImageMouseEnter"
                @mouseleave="handleImageMouseLeave"
                @mousemove="handleImageMouseMove"
                @click="toggleZoom"
            >
              <img
                  v-if="currentImage"
                  :src="currentImage"
                  :alt="selectedProduct.name"
                  class="gallery-main-image"
                  :class="{ 'zoomed': isZoomed }"
                  :style="mainImageStyle"
              />
              <div v-else class="gallery-main-placeholder">
                <ShoppingCart :size="48" style="color: #c5bfb5;" />
              </div>

              <!-- Zoom Lens -->
              <div v-if="isZoomed" class="zoom-lens" :style="zoomLensStyle"></div>

              <!-- Zoom Hint -->
              <div v-if="!isZoomed && hasMultipleImages" class="zoom-hint">
                <span>Click to zoom</span>
              </div>
            </div>

            <!-- Navigation Arrows -->
            <button
                v-if="hasMultipleImages && currentImageIndex > 0"
                class="gallery-nav-arrow prev"
                @click="prevImage"
            >
              <ChevronLeft :size="20" />
            </button>
            <button
                v-if="hasMultipleImages && currentImageIndex < productImages.length - 1"
                class="gallery-nav-arrow next"
                @click="nextImage"
            >
              <ChevronRight :size="20" />
            </button>

            <!-- Image Counter -->
            <div v-if="hasMultipleImages" class="gallery-counter">
              {{ currentImageIndex + 1 }} / {{ productImages.length }}
            </div>

            <!-- Category Badge -->
            <span class="product-modal-badge">{{ selectedProduct.category?.name }}</span>
          </div>

          <!-- Thumbnail Strip -->
          <div v-if="hasMultipleImages" class="gallery-thumbnails">
            <div
                v-for="(image, index) in productImages"
                :key="index"
                class="gallery-thumbnail"
                :class="{ 'active': currentImageIndex === index }"
                @click="selectImage(index)"
            >
              <img :src="image" :alt="`${selectedProduct.name} ${index + 1}`" />
            </div>
          </div>
        </div>

        <!-- Right Column - Product Info (Enhanced) -->
        <div class="product-modal-details-scroll">
          <!-- Breadcrumb -->
          <div class="product-breadcrumb">
            <span>Home</span>
            <ArrowRight :size="12" />
            <span>Fashion</span>
            <ArrowRight :size="12" />
            <span>{{ selectedProduct.category?.name || 'Dress' }}</span>
          </div>

          <!-- Header -->
          <div class="product-header">
            <h2 class="product-name">{{ selectedProduct.name }}</h2>
            <div class="product-price-large">{{ formatPrice(selectedProduct.price) }}</div>
          </div>

          <!-- Description -->
          <p class="product-description">{{ selectedProduct.description || 'Premium quality product crafted with attention to detail.' }}</p>

          <!-- Color Selection -->
          <div v-if="selectedProduct.colors && selectedProduct.colors.length" class="product-section">
            <h3 class="product-section-title">COLOR</h3>
            <div class="color-pills">
              <button
                  v-for="color in selectedProduct.colors"
                  :key="color"
                  class="color-pill"
                  :class="{ 'selected': selectedColor === color }"
                  @click="selectedColor = color"
              >
                <span class="color-dot" :style="{ backgroundColor: color.toLowerCase() }"></span>
                {{ color }}
              </button>
            </div>
          </div>

          <!-- Size Selection -->
          <div v-if="selectedProduct.sizes && selectedProduct.sizes.length" class="product-section">
            <h3 class="product-section-title">SIZE</h3>
            <div class="size-pills">
              <button
                  v-for="size in selectedProduct.sizes"
                  :key="size"
                  class="size-pill"
                  :class="{ 'selected': selectedSize === size }"
                  @click="selectedSize = size"
              >
                {{ size }}
              </button>
            </div>
          </div>

          <!-- Stock Status -->
          <div class="stock-status" :class="{ 'low': selectedProduct.stock < 10 }">
            <span class="stock-dot"></span>
            {{ selectedProduct.stock > 0 ? `${selectedProduct.stock} in stock` : 'Out of stock' }}
          </div>

          <!-- Quantity & Add to Cart -->
          <div class="product-actions">
            <div class="quantity-wrapper">
              <button
                  class="quantity-btn"
                  @click="detailQuantity = Math.max(1, detailQuantity - 1)"
                  :disabled="detailQuantity <= 1"
              >
                <Minus :size="16" />
              </button>
              <span class="quantity-value">{{ detailQuantity }}</span>
              <button
                  class="quantity-btn"
                  @click="detailQuantity = Math.min(selectedProduct.stock, detailQuantity + 1)"
                  :disabled="detailQuantity >= selectedProduct.stock"
              >
                <Plus :size="16" />
              </button>
            </div>

            <button
                class="add-to-cart-large"
                :disabled="!selectedSize || !selectedColor || selectedProduct.stock === 0"
                @click="addToCart"
            >
              <ShoppingCart :size="18" />
              Add to Cart
              <span class="cart-total">{{ formatPrice(selectedProduct.price * detailQuantity) }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- CART DRAWER                                                             -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="isCartOpen" class="modal-overlay side-right" @click.self="isCartOpen = false">
    <div class="modal-side" style="background: white; width: 100%; max-width: 440px; height: 100vh; display: flex; flex-direction: column;">
      <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; border-bottom: 1px solid #ede8e2;">
        <h2 class="font-display" style="font-size: 1.3rem; font-weight: 700; margin: 0;">Your Cart</h2>
        <button @click="isCartOpen = false" style="background: none; border: none; cursor: pointer; color: var(--charcoal);">
          <X :size="20" />
        </button>
      </div>

      <div style="flex: 1; overflow-y: auto; padding: 20px 24px;">
        <div v-if="cart.length === 0" style="text-align: center; padding: 48px 0; color: var(--text-muted);">
          <ShoppingCart :size="36" style="margin-bottom: 12px; opacity: 0.4;" />
          <p style="font-size: 0.85rem; margin: 0;">Your cart is empty</p>
        </div>
        <div v-for="(item, index) in cart" :key="index" style="display: flex; gap: 14px; padding: 16px 0; border-bottom: 1px solid #f0ebe5;">
          <div style="width: 64px; height: 64px; border-radius: 8px; background: var(--cream-dark); overflow: hidden; flex-shrink: 0;">
            <img v-if="getProductImage(item.product)" :src="getProductImage(item.product)" style="width: 100%; height: 100%; object-fit: cover;" />
            <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"><ShoppingCart :size="18" style="color: #c5bfb5;"/></div>
          </div>
          <div style="flex: 1; min-width: 0;">
            <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0 0 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ item.product.name }}</p>
            <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 8px;">{{ item.color }} · Size {{ item.size }}</p>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <button class="qty-btn" style="width: 26px; height: 26px;" @click="updateQuantity(index, -1)">
                  <Minus :size="12" style="color: var(--charcoal);" />
                </button>
                <span style="font-size: 0.8rem; font-weight: 600;">{{ item.quantity }}</span>
                <button class="qty-btn" style="width: 26px; height: 26px;" @click="updateQuantity(index, 1)">
                  <Plus :size="12" style="color: var(--charcoal);" />
                </button>
              </div>
              <span style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal);">{{ formatPrice(item.product.price * item.quantity) }}</span>
            </div>
          </div>
          <button @click="removeFromCart(index)" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px;" title="Remove">
            <X :size="16" />
          </button>
        </div>
      </div>

      <div v-if="cart.length > 0" style="padding: 20px 24px; border-top: 1px solid #ede8e2; background: var(--cream);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <span style="font-size: 0.85rem; color: var(--text-muted);">Total</span>
          <span style="font-size: 1.1rem; font-weight: 700; color: var(--charcoal);">{{ formatPrice(cartTotal) }}</span>
        </div>
        <button class="btn-primary" style="width: 100%; justify-content: center;" @click="openCheckout">
          Proceed to Checkout <ArrowRight :size="16" />
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- CHECKOUT MODAL                                                          -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="isCheckoutOpen" class="modal-overlay" @click.self="isCheckoutOpen = false">
    <div class="modal-content" style="background: white; border-radius: 12px; max-width: 580px; width: 90vw; max-height: 90vh; overflow-y: auto; position: relative;">
      <button @click="isCheckoutOpen = false" style="position: absolute; top: 16px; right: 16px; z-index: 2; background: none; border: none; cursor: pointer; color: var(--charcoal);">
        <X :size="20" />
      </button>

      <div style="padding: 32px;">
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; margin: 0 0 4px; color: var(--charcoal);">Checkout</h2>
        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0 0 24px;">Fill in your details and we'll send an M-Pesa prompt to your phone.</p>

        <div style="background: var(--cream); border-radius: 8px; padding: 16px; margin-bottom: 24px;">
          <div v-for="(item, i) in cart" :key="i" style="display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--charcoal-soft); padding: 4px 0;">
            <span>{{ item.product.name }} <span style="color: var(--text-muted);">({{ item.color }}, {{ item.size }}) x{{ item.quantity }}</span></span>
            <span style="font-weight: 600;">{{ formatPrice(item.product.price * item.quantity) }}</span>
          </div>
          <div style="border-top: 1px solid #d5cfc8; margin-top: 10px; padding-top: 10px; display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 700; color: var(--charcoal);">
            <span>Total</span>
            <span>{{ formatPrice(cartTotal) }}</span>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div>
            <label class="shop-label"><User :size="12" style="vertical-align: middle; margin-right: 4px;" />Name</label>
            <input v-model="orderForm.customer_name" class="shop-input" :class="{ error: orderForm.errors.customer_name }" placeholder="e.g. John Doe" />
            <span v-if="orderForm.errors.customer_name" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.customer_name }}</span>
          </div>
          <div>
            <label class="shop-label"><Phone :size="12" style="vertical-align: middle; margin-right: 4px;" />M-Pesa No</label>
            <input v-model="orderForm.mpesa_number" class="shop-input" :class="{ error: orderForm.errors.mpesa_number }" placeholder="e.g. 0712345678" type="tel" />
            <span v-if="orderForm.errors.mpesa_number" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.mpesa_number }}</span>
          </div>
          <div style="grid-column: span 2;">
            <label class="shop-label"><MapPin :size="12" style="vertical-align: middle; margin-right: 4px;" />Town</label>
            <input v-model="orderForm.town" class="shop-input" :class="{ error: orderForm.errors.town }" placeholder="e.g. Nairobi" />
            <span v-if="orderForm.errors.town" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.town }}</span>
          </div>
          <div style="grid-column: span 2;">
            <label class="shop-label"><Truck :size="12" style="vertical-align: middle; margin-right: 4px;" />Delivery / Pickup Details</label>
            <textarea v-model="orderForm.description" class="shop-input" :class="{ error: orderForm.errors.description }" placeholder="Describe your delivery address or pickup point..." rows="2" style="resize: vertical;"></textarea>
            <span v-if="orderForm.errors.description" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.description }}</span>
          </div>
        </div>

        <p style="font-size: 0.72rem; color: var(--text-muted); margin: 20px 0; line-height: 1.5;">
          An M-Pesa payment request of <strong style="color: var(--charcoal);">{{ formatPrice(cartTotal) }}</strong> will be sent to your phone immediately after placing the order.
        </p>

        <button
            class="btn-primary"
            style="width: 100%; justify-content: center;"
            :disabled="orderForm.processing"
            @click="submitOrder"
        >
          <Loader2 v-if="orderForm.processing" :size="16" class="animate-spin" />
          {{ orderForm.processing ? 'Placing Order...' : 'Place Order & Pay' }} <ArrowRight v-if="!orderForm.processing" :size="16" />
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- ORDER SUCCESS / PAYMENT STATUS MODAL                                    -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="isSuccessOpen" class="modal-overlay">
    <div class="modal-content" style="background: white; border-radius: 12px; max-width: 420px; width: 90vw; padding: 44px 32px; text-align: center;">

      <!-- Pending Payment -->
      <template v-if="paymentStatus === 'pending'">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #fff8e6; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <Loader2 :size="32" style="color: #f0a500;" class="animate-spin" />
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Waiting for Payment</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          {{ orderSuccess?.stk_message || 'Check your phone for the M-Pesa prompt and enter your PIN to complete payment.' }}
        </p>
        <div style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 16px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Amount</p>
          <p style="font-size: 1.1rem; font-weight: 700; color: var(--charcoal); margin: 0;">{{ formatPrice(orderSuccess?.amount || 0) }}</p>
        </div>
        <p style="font-size: 0.72rem; color: var(--text-muted); margin: 0;">
          <Loader2 :size="12" class="animate-spin" style="display: inline; vertical-align: middle; margin-right: 4px;" />
          Checking payment status...
        </p>
      </template>

      <!-- Payment Successful -->
      <template v-else-if="paymentStatus === 'completed'">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #edfbf0; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Payment Successful!</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          Thank you for your purchase. Your order is being processed.
        </p>
        <div style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 16px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">M-Pesa Receipt</p>
          <p style="font-size: 0.9rem; font-weight: 600; color: var(--charcoal); margin: 0;">{{ mpesaReceipt }}</p>
        </div>
        <div v-if="orderSuccess" style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 24px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Order Reference</p>
          <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0; word-break: break-all;">{{ orderSuccess.uuid }}</p>
        </div>
        <button class="btn-primary" style="width: 100%; justify-content: center;" @click="closeSuccessModal">
          Continue Shopping
        </button>
      </template>

      <!-- Payment Failed -->
      <template v-else-if="paymentStatus === 'failed'">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #fdeaea; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <X :size="32" style="color: #d63031;" />
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Payment Failed</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          The payment was not completed. This could be due to insufficient funds, wrong PIN, or cancelled request.
        </p>
        <div v-if="orderSuccess" style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 24px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Order Reference</p>
          <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0; word-break: break-all;">{{ orderSuccess.uuid }}</p>
          <p style="font-size: 0.72rem; color: var(--text-muted); margin: 8px 0 0;">Contact us with this reference to retry payment.</p>
        </div>
        <button class="btn-outline" style="width: 100%; justify-content: center;" @click="closeSuccessModal">
          Close
        </button>
      </template>

      <!-- Initial Success (STK not sent or no polling) -->
      <template v-else>
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #edfbf0; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Order Placed!</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          {{ orderSuccess?.stk_sent ? orderSuccess.stk_message : 'Your order has been received. We will contact you shortly regarding payment.' }}
        </p>
        <div v-if="orderSuccess" style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 24px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Order Reference</p>
          <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0; word-break: break-all;">{{ orderSuccess.uuid }}</p>
        </div>
        <button class="btn-outline" style="width: 100%; justify-content: center;" @click="closeSuccessModal">
          Continue Shopping
        </button>
      </template>
    </div>
  </div>
</template>

<!-- ─── STYLES ────────────────────────────────────────────────────────── -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');

:root {
  --cream: #f5f0eb;
  --cream-dark: #ebe5dc;
  --charcoal: #1a1a1a;
  --charcoal-soft: #3a3a3a;
  --accent: #c8a96e;
  --accent-light: #dcc49a;
  --accent-dark: #a07b3e;
  --text-muted: #7a7168;
}

* { box-sizing: border-box; }

body {
  font-family: 'Inter', sans-serif;
  background: var(--cream);
  color: var(--charcoal);
  -webkit-font-smoothing: antialiased;
  margin: 0;
  padding: 0;
}

.font-display { font-family: 'Playfair Display', serif; }

::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--accent-light); border-radius: 3px; }

/* Parallax Hero Styles */
.hero-parallax {
  perspective: 1px;
  transform-style: preserve-3d;
}

.floating-shape {
  position: absolute;
  border-radius: 50%;
  opacity: 0.08;
  pointer-events: none;
  will-change: transform;
}

.floating-shape.shape-1 {
  width: 300px;
  height: 300px;
  background: var(--accent);
  top: -50px;
  right: 10%;
  filter: blur(60px);
}

.floating-shape.shape-2 {
  width: 200px;
  height: 200px;
  background: white;
  bottom: 20%;
  left: 5%;
  filter: blur(40px);
}

.scroll-indicator {
  position: absolute;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 2;
  transition: opacity 0.3s;
}

.scroll-arrow {
  width: 24px;
  height: 24px;
  border-right: 2px solid rgba(255,255,255,0.5);
  border-bottom: 2px solid rgba(255,255,255,0.5);
  transform: rotate(45deg);
  animation: scrollBounce 2s infinite;
}

@keyframes scrollBounce {
  0%, 20%, 50%, 80%, 100% { transform: rotate(45deg) translateY(0); }
  40% { transform: rotate(45deg) translateY(8px); }
  60% { transform: rotate(45deg) translateY(4px); }
}

/* Product Card Hover Effects */
.product-card {
  background: white;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid #ede8e2;
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
}

.product-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 12px 40px rgba(0,0,0,0.1);
}

.product-card .product-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.product-card:hover .product-img {
  transform: scale(1.05);
}

.modal-overlay {
  position: fixed; inset: 0; z-index: 50;
  background: rgba(26,26,26,0.55);
  backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn 0.2s ease;
}
.modal-overlay.side-right {
  align-items: stretch; justify-content: flex-end;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes slideRight { from { transform: translateX(100%); } to { transform: translateX(0); } }

.modal-content { animation: slideUp 0.28s cubic-bezier(.22,1,.36,1); }
.modal-side { animation: slideRight 0.3s cubic-bezier(.22,1,.36,1); }

.tag-pill {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--cream-dark);
  color: var(--charcoal-soft);
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s;
}
.tag-pill:hover { border-color: var(--accent); }
.tag-pill.active {
  background: var(--charcoal);
  color: var(--cream);
  border-color: var(--charcoal);
}

.btn-primary {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  background: var(--charcoal);
  color: var(--cream);
  font-family: 'Inter', sans-serif;
  font-size: 0.82rem;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 14px 28px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}
.btn-primary:hover { background: var(--charcoal-soft); }
.btn-primary:active { transform: scale(0.97); }
.btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }

.btn-outline {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  background: transparent;
  color: var(--charcoal);
  font-family: 'Inter', sans-serif;
  font-size: 0.82rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  padding: 12px 24px;
  border: 1px solid var(--charcoal);
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-outline:hover { background: var(--charcoal); color: var(--cream); }

.shop-input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #d5cfc8;
  border-radius: 6px;
  font-family: 'Inter', sans-serif;
  font-size: 0.85rem;
  background: white;
  color: var(--charcoal);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.shop-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,169,110,0.18); }
.shop-input::placeholder { color: var(--text-muted); }
.shop-input.error { border-color: #d63031; }

.shop-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.option-btn {
  padding: 8px 14px;
  border: 1px solid #d5cfc8;
  border-radius: 6px;
  background: white;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}
.option-btn:hover { border-color: var(--accent); }
.option-btn.selected {
  background: var(--charcoal);
  color: white;
  border-color: var(--charcoal);
}

.qty-btn {
  width: 32px; height: 32px;
  border: 1px solid #d5cfc8;
  background: white;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}
.qty-btn:hover { border-color: var(--charcoal); background: var(--cream-dark); }

.animate-spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* ENHANCED PRODUCT MODAL WITH GALLERY STYLES                                  */
/* ═══════════════════════════════════════════════════════════════════════════ */
.product-modal {
  background: white;
  border-radius: 16px;
  max-width: 92vw;
  max-height: 90vh;
  overflow: hidden;
  position: relative;
  animation: slideUp 0.28s cubic-bezier(.22,1,.36,1);
  box-shadow: 0 25px 60px rgba(0,0,0,0.25);
}

.product-modal-wide {
  max-width: 1000px;
  width: 90vw;
  max-height: 85vh;
  overflow: hidden;
}

.product-modal-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  height: 100%;
  overflow: hidden;
}

.modal-close-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 20;
  background: rgba(255,255,255,0.95);
  backdrop-filter: blur(8px);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
  transition: transform 0.2s, box-shadow 0.2s;
  color: var(--charcoal);
}
.modal-close-btn:hover {
  transform: scale(1.08);
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}

/* Gallery Section */
.product-modal-gallery {
  padding: 24px;
  background: #faf8f5;
  display: flex;
  flex-direction: column;
  gap: 20px;
  height: 100%;
  overflow-y: auto;
}

.gallery-main-container {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  border-radius: 16px;
  overflow: hidden;
  background: white;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.gallery-main-image-wrapper {
  width: 100%;
  height: 100%;
  position: relative;
  cursor: zoom-in;
  overflow: hidden;
}

.gallery-main-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.gallery-main-image.zoomed {
  transition: transform 0.1s ease;
  cursor: grab;
}

.gallery-main-image.zoomed:active {
  cursor: grabbing;
}

.gallery-main-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f0ebe5 0%, #e5dfd6 100%);
}

.product-modal-badge {
  position: absolute;
  bottom: 16px;
  left: 16px;
  background: rgba(255,255,255,0.95);
  backdrop-filter: blur(8px);
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--accent-dark);
  z-index: 5;
}

/* Zoom Lens */
.zoom-lens {
  position: absolute;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  border: 2px solid white;
  background: rgba(255, 255, 255, 0.3);
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
  pointer-events: none;
  transition: all 0.05s ease;
  z-index: 10;
}

.zoom-hint {
  position: absolute;
  bottom: 16px;
  right: 16px;
  background: rgba(0, 0, 0, 0.6);
  color: white;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 12px;
  backdrop-filter: blur(4px);
  z-index: 5;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 0.8; }
  50% { opacity: 0.5; }
}

/* Gallery Navigation */
.gallery-nav-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: white;
  border: none;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  z-index: 15;
  color: var(--charcoal);
}

.gallery-nav-arrow:hover {
  transform: translateY(-50%) scale(1.1);
  background: #f8f9fa;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.gallery-nav-arrow.prev {
  left: 16px;
}

.gallery-nav-arrow.next {
  right: 16px;
}

.gallery-counter {
  position: absolute;
  bottom: 16px;
  right: 16px;
  background: rgba(0, 0, 0, 0.6);
  color: white;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  backdrop-filter: blur(4px);
  z-index: 5;
}

/* Thumbnails */
.gallery-thumbnails {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding: 4px 0 8px;
}

.gallery-thumbnail {
  width: 70px;
  height: 70px;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s;
  flex-shrink: 0;
  background: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.gallery-thumbnail:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.gallery-thumbnail.active {
  border-color: var(--accent);
  transform: scale(1.02);
}

.gallery-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Right Column - Enhanced Product Info */
.product-modal-details-scroll {
  padding: 32px;
  overflow-y: auto;
  background: white;
  height: 100%;
}

.product-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-muted);
  font-size: 12px;
  margin-bottom: 24px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.product-breadcrumb span:not(:last-child) {
  color: var(--accent);
  cursor: pointer;
}

.product-breadcrumb span:not(:last-child):hover {
  text-decoration: underline;
}

.product-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  gap: 20px;
}

.product-name {
  font-size: 28px;
  font-weight: 700;
  color: var(--charcoal);
  line-height: 1.2;
  margin: 0;
  font-family: 'Playfair Display', serif;
}

.product-price-large {
  font-size: 24px;
  font-weight: 700;
  color: var(--charcoal);
  white-space: nowrap;
  background: var(--cream);
  padding: 8px 16px;
  border-radius: 12px;
}

.product-description {
  font-size: 14px;
  color: var(--text-muted);
  line-height: 1.7;
  margin-bottom: 28px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f0ebe5;
}

.product-section {
  margin-bottom: 24px;
}

.product-section-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 12px 0;
}

/* Color Pills */
.color-pills {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.color-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 1.5px solid #e5dfd6;
  border-radius: 40px;
  background: white;
  font-size: 13px;
  font-weight: 500;
  color: var(--charcoal-soft);
  cursor: pointer;
  transition: all 0.2s;
}

.color-pill:hover {
  border-color: var(--accent);
  background: #faf8f5;
  transform: translateY(-1px);
}

.color-pill.selected {
  border-color: var(--charcoal);
  background: var(--charcoal);
  color: white;
}

.color-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  display: inline-block;
}

/* Size Pills */
.size-pills {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.size-pill {
  min-width: 50px;
  padding: 10px 14px;
  border: 1.5px solid #e5dfd6;
  border-radius: 12px;
  background: white;
  font-size: 14px;
  font-weight: 600;
  color: var(--charcoal-soft);
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}

.size-pill:hover {
  border-color: var(--accent);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.size-pill.selected {
  border-color: var(--charcoal);
  background: var(--charcoal);
  color: white;
}

/* Stock Status */
.stock-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #f0f9f0;
  border-radius: 40px;
  font-size: 13px;
  font-weight: 500;
  color: #2e7d32;
  margin-bottom: 24px;
}

.stock-status.low {
  background: #fff3e0;
  color: #e65100;
}

.stock-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  animation: pulse 1.5s infinite;
}

/* Product Actions */
.product-actions {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-top: 20px;
}

.quantity-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  background: #f8f8f8;
  padding: 8px;
  border-radius: 50px;
}

.quantity-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: white;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  color: var(--charcoal);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.quantity-btn:hover:not(:disabled) {
  background: var(--charcoal);
  color: white;
  transform: scale(1.05);
}

.quantity-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.quantity-value {
  font-size: 18px;
  font-weight: 600;
  min-width: 40px;
  text-align: center;
}

.add-to-cart-large {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  width: 100%;
  padding: 16px 24px;
  background: linear-gradient(135deg, var(--charcoal), #2d2d2d);
  color: white;
  border: none;
  border-radius: 50px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.add-to-cart-large:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.3);
  background: var(--charcoal-soft);
}

.add-to-cart-large:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #9ca3af;
  transform: none;
  box-shadow: none;
}

.cart-total {
  background: rgba(255, 255, 255, 0.2);
  padding: 4px 12px;
  border-radius: 30px;
  font-size: 14px;
  font-weight: 500;
}

/* Scrollbar for details panel */
.product-modal-details-scroll::-webkit-scrollbar {
  width: 4px;
}

.product-modal-details-scroll::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.product-modal-details-scroll::-webkit-scrollbar-thumb {
  background: var(--accent-light);
  border-radius: 2px;
}

/* WhatsApp Floating Button */
.whatsapp-fab {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 45;
  width: 56px;
  height: 56px;
  background: #25D366;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px rgba(37, 211, 102, 0.4);
  transition: transform 0.2s, box-shadow 0.2s;
  text-decoration: none;
}
.whatsapp-fab:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 24px rgba(37, 211, 102, 0.5);
}
.whatsapp-fab:active {
  transform: scale(0.95);
}

/* Responsive */
@media (max-width: 768px) {
  .product-modal-grid {
    grid-template-columns: 1fr;
    overflow-y: auto;
  }

  .product-modal-gallery {
    padding: 16px;
  }

  .product-modal-details-scroll {
    padding: 24px;
  }

  .gallery-thumbnail {
    width: 60px;
    height: 60px;
  }

  .product-name {
    font-size: 22px;
  }

  .product-price-large {
    font-size: 18px;
    padding: 6px 12px;
  }
}
</style>