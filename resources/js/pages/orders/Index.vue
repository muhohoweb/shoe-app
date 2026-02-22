<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit, Trash2, Eye, Package, User, Phone, MapPin, PlusCircle, X, LayoutGrid, Table as TableIcon } from 'lucide-vue-next';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Card,
  CardContent,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { toast } from 'vue-sonner'
import { ref, computed, watch } from 'vue'
import draggable from 'vuedraggable'

interface Product {
  id: number
  name: string
  price: number
  colors: string[] | null
  sizes: string[] | null
  image_path?: string
}

interface Item {
  id: number
  product_id: number
  size: string | null
  color: string | null
  price: number
  quantity: number
  product?: {
    id: number
    name: string
    images?: { path: string }[]
  }
}

interface OrderItem {
  product_id: number
  quantity: number
  price: number
  size: string | null
  color: string | null
}

interface Order {
  id: number
  uuid: string
  customer_name: string | null
  mpesa_number: string
  mpesa_code: string | null
  amount: number
  payment_status: 'pending' | 'paid' | 'failed'
  tracking_number: string | null
  town: string
  description: string
  status: 'pending' | 'processing' | 'completed' | 'cancelled'
  items: Item[]
  created_at: string
}

type OrderStatus = Order['status']

const props = defineProps<{
  orders?: { data: Order[]; links: any[] }
  products?: Product[]
}>()

const updatingOrderId = ref<number | null>(null)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'Orders' },
]

// View toggle
const viewMode = ref<'table' | 'kanban'>('table')

const columns: { key: OrderStatus; label: string; color: string; bg: string; hoverBg: string }[] = [
  { key: 'pending', label: 'Pending', color: 'border-yellow-400', bg: 'bg-yellow-50 dark:bg-yellow-900/20', hoverBg: 'bg-yellow-100/50 dark:bg-yellow-900/40' },
  { key: 'processing', label: 'Processing', color: 'border-blue-400', bg: 'bg-blue-50 dark:bg-blue-900/20', hoverBg: 'bg-blue-100/50 dark:bg-blue-900/40' },
  { key: 'completed', label: 'Completed', color: 'border-green-400', bg: 'bg-green-50 dark:bg-green-900/20', hoverBg: 'bg-green-100/50 dark:bg-green-900/40' },
  { key: 'cancelled', label: 'Cancelled', color: 'border-red-400', bg: 'bg-red-50 dark:bg-red-900/20', hoverBg: 'bg-red-100/50 dark:bg-red-900/40' },
]

const paymentStatusColors: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

const statusColors: Record<OrderStatus, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

// Drag state
const isDragging = ref(false)

// Kanban state
const kanbanData = ref<Record<OrderStatus, Order[]>>({
  pending: [],
  processing: [],
  completed: [],
  cancelled: [],
})

const initializeKanban = () => {
  const orders = props.orders?.data || []
  kanbanData.value = {
    pending: [...orders.filter(o => o.status === 'pending')],
    processing: [...orders.filter(o => o.status === 'processing')],
    completed: [...orders.filter(o => o.status === 'completed')],
    cancelled: [...orders.filter(o => o.status === 'cancelled')],
  }
}

initializeKanban()

watch(() => props.orders, initializeKanban, { deep: true })

const getColumnCount = (status: OrderStatus) => kanbanData.value[status].length

const handleDragStart = () => {
  isDragging.value = true
}

const handleDragEnd = () => {
  isDragging.value = false
}

const handleDragChange = (newStatus: OrderStatus, evt: any) => {
  if (evt.added) {
    const order = evt.added.element as Order
    updatingOrderId.value = order.id  // ← add this

    router.put(`/orders/${order.id}`, {
      status: newStatus,
      payment_status: order.payment_status,
      tracking_number: order.tracking_number || '',
      send_dispatch: newStatus === 'completed',
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Order Updated', {
          description: newStatus === 'completed'
              ? `Order completed. Dispatch notification sent.`
              : `Order moved to ${newStatus}.`,
        })
        updatingOrderId.value = null  // ← clear
      },
      onError: () => {
        toast.error('Update Failed')
        initializeKanban()
        updatingOrderId.value = null  // ← clear
      },
    })
  }
}

// ─── Create Modal ───────────────────────────────────────────────
const isCreateDialogOpen = ref(false)
const createForm = useForm({
  customer_name: '',
  mpesa_number: '',
  mpesa_code: '',
  amount: 0,
  payment_status: 'pending',
  tracking_number: '',
  town: '',
  description: '',
  status: 'pending',
  items: [] as OrderItem[],
})

const currentItem = ref({
  product_id: null as number | null,
  quantity: 1,
  price: 0,
  size: null as string | null,
  color: null as string | null,
})

const selectedProduct = computed(() => {
  if (!currentItem.value.product_id) return null
  return props.products?.find(p => p.id === currentItem.value.product_id)
})

const availableSizes = computed(() => selectedProduct.value?.sizes || [])
const availableColors = computed(() => selectedProduct.value?.colors || [])

const addItem = () => {
  if (!currentItem.value.product_id || currentItem.value.quantity < 1) {
    toast.error('Please select a product and quantity')
    return
  }

  createForm.items.push({
    product_id: currentItem.value.product_id,
    quantity: currentItem.value.quantity,
    price: currentItem.value.price,
    size: currentItem.value.size,
    color: currentItem.value.color,
  })

  currentItem.value = {
    product_id: null,
    quantity: 1,
    price: 0,
    size: null,
    color: null,
  }

  updateTotalAmount()
}

const removeItem = (index: number) => {
  createForm.items.splice(index, 1)
  updateTotalAmount()
}

const updateTotalAmount = () => {
  createForm.amount = createForm.items.reduce((sum, item) => sum + (item.price * item.quantity), 0)
}

const onProductSelect = (productId: string) => {
  const product = props.products?.find(p => p.id === Number(productId))
  if (product) {
    currentItem.value.product_id = product.id
    currentItem.value.price = product.price
    currentItem.value.size = null
    currentItem.value.color = null
  }
}

const getProductName = (productId: number) => {
  return props.products?.find(p => p.id === productId)?.name || 'Unknown'
}

const openCreateDialog = () => {
  createForm.reset()
  createForm.items = []
  createForm.clearErrors()
  currentItem.value = {
    product_id: null,
    quantity: 1,
    price: 0,
    size: null,
    color: null,
  }
  isCreateDialogOpen.value = true
}

const closeCreateDialog = () => {
  isCreateDialogOpen.value = false
  createForm.reset()
  createForm.items = []
}

const handleCreateSubmit = () => {
  if (createForm.items.length === 0) {
    toast.error('Please add at least one item')
    return
  }

  createForm.post('/orders', {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Order Created')
      closeCreateDialog()
    },
    onError: () => toast.error('Creation Failed'),
  })
}

// ─── View Modal ─────────────────────────────────────────────────
const isViewDialogOpen = ref(false)
const viewingOrder = ref<Order | null>(null)

const openViewDialog = (order: Order) => {
  viewingOrder.value = order
  isViewDialogOpen.value = true
}

const closeViewDialog = () => {
  isViewDialogOpen.value = false
  viewingOrder.value = null
}

// ─── Edit Modal ─────────────────────────────────────────────────
const isEditDialogOpen = ref(false)
const editingOrder = ref<Order | null>(null)
const editForm = useForm({
  status: '',
  payment_status: '',
  tracking_number: '',
  send_dispatch: false,  // add this
})

const openEditDialog = (order: Order) => {
  editingOrder.value = order
  editForm.status = order.status
  editForm.payment_status = order.payment_status
  editForm.tracking_number = order.tracking_number || ''
  editForm.send_dispatch = order.status === 'completed'
  editForm.clearErrors()
  isEditDialogOpen.value = true
}

const closeEditDialog = () => {
  isEditDialogOpen.value = false
  editingOrder.value = null
  editForm.reset()
}

const handleEditSubmit = () => {
  const payload = {
    status: editForm.status,
    payment_status: editForm.payment_status,
    tracking_number: editForm.tracking_number,
    send_dispatch: editForm.status === 'completed' && editForm.payment_status === 'paid',
  }

  editForm.send_dispatch = payload.send_dispatch

  updatingOrderId.value = editingOrder.value!.id  // ← set

  editForm.put(`/orders/${editingOrder.value!.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Order Updated')
      updatingOrderId.value = null  // ← clear
      closeEditDialog()
    },
    onError: (errors) => {
      console.log(errors)
      updatingOrderId.value = null  // ← clear
    },
  })
}

// ─── Delete Modal ───────────────────────────────────────────────
const isDeleteDialogOpen = ref(false)
const deletingOrder = ref<Order | null>(null)

const openDeleteDialog = (order: Order) => {
  deletingOrder.value = order
  isDeleteDialogOpen.value = true
}

const handleDeleteConfirm = () => {
  if (!deletingOrder.value) return
  router.delete(`/orders/${deletingOrder.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Order Deleted')
      isDeleteDialogOpen.value = false
      deletingOrder.value = null
    },
    onError: () => toast.error('Delete Failed'),
  })
}

const closeDeleteDialog = () => {
  isDeleteDialogOpen.value = false
  deletingOrder.value = null
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-KE', {
    month: 'short',
    day: 'numeric',
  })
}
</script>

<template #item="{ element: order }">
  <div
      class="kanban-card relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-200 cursor-grab active:cursor-grabbing overflow-hidden"
      :class="{ 'opacity-60 pointer-events-none': updatingOrderId === order.id }"
  >
    <!-- Loading Overlay -->
    <div
        v-if="updatingOrderId === order.id"
        class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 dark:bg-gray-800/60 rounded-lg"
    >
      <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
    </div>

    <!-- Product Image Header -->
    <div class="relative h-28 sm:h-32 bg-gray-200 dark:bg-gray-700 overflow-hidden">
      <img
          v-if="order.items?.[0]?.product?.images?.[0]?.path"
          :src="order.items[0].product.images[0].path"
          :alt="order.items[0].product?.name"
          class="w-full h-full object-cover"
      />
      <div v-else class="w-full h-full flex items-center justify-center">
        <Package class="h-10 w-10 sm:h-12 sm:w-12 text-gray-400" />
      </div>
      <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70"></div>

      <div class="absolute top-2 left-2 right-2 flex items-start justify-between">
        <span class="font-mono text-xs text-white font-semibold bg-black/30 backdrop-blur-sm px-2 py-1 rounded">
          #{{ order.uuid.slice(0, 8) }}
        </span>
        <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium backdrop-blur-sm', paymentStatusColors[order.payment_status]]">
          {{ order.payment_status }}
        </span>
      </div>

      <div class="absolute bottom-2 left-2">
        <span class="font-bold text-white text-base sm:text-lg drop-shadow-lg">
          KES {{ Number(order.amount).toLocaleString() }}
        </span>
      </div>

      <div class="absolute bottom-2 right-2">
        <span class="inline-flex items-center gap-1 text-xs text-white bg-black/40 backdrop-blur-sm px-2 py-1 rounded font-medium">
          <Package class="h-3 w-3" />
          {{ order.items?.length || 0 }}
        </span>
      </div>
    </div>

    <!-- Card Content -->
    <div class="p-3">
      <div class="space-y-1 mb-3">
        <div class="flex items-center gap-2 text-xs sm:text-sm">
          <User class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-gray-400" />
          <span class="font-medium truncate">{{ order.customer_name || 'N/A' }}</span>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
          <Phone class="h-3 w-3" />
          <span>{{ order.mpesa_number }}</span>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
          <MapPin class="h-3 w-3" />
          <span>{{ order.town }}</span>
        </div>
      </div>

      <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
        <span class="text-xs text-gray-400">{{ formatDate(order.created_at) }}</span>
        <div class="flex items-center gap-1">
          <button @click.stop="openViewDialog(order)" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
            <Eye class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-gray-500" />
          </button>
          <button @click.stop="openEditDialog(order)" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
            <Edit class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-500" />
          </button>
          <button @click.stop="openDeleteDialog(order)" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
            <Trash2 class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-red-500" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.kanban-ghost {
  opacity: 0.4;
  background: hsl(var(--primary) / 0.1) !important;
  border: 2px dashed hsl(var(--primary)) !important;
  border-radius: 0.5rem;
}

.kanban-drag {
  opacity: 1 !important;
  transform: rotate(3deg) scale(1.02);
  box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.15) !important;
  z-index: 9999;
}

.kanban-chosen {
  box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1) !important;
}

.kanban-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.sortable-ghost {
  opacity: 0;
}
</style>