<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit, Trash2, Eye, Package, User, Phone, MapPin, PlusCircle, X } from 'lucide-vue-next';
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

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'Orders' },
]

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
      },
      onError: () => {
        toast.error('Update Failed')
        initializeKanban()
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
})

const openEditDialog = (order: Order) => {
  editingOrder.value = order
  editForm.status = order.status
  editForm.payment_status = order.payment_status
  editForm.tracking_number = order.tracking_number || ''
  editForm.clearErrors()
  isEditDialogOpen.value = true
}

const closeEditDialog = () => {
  isEditDialogOpen.value = false
  editingOrder.value = null
  editForm.reset()
}

const handleEditSubmit = () => {
  if (!editingOrder.value) return
  editForm.put(`/orders/${editingOrder.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Order Updated')
      closeEditDialog()
    },
    onError: () => toast.error('Update Failed'),
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

<template>
  <Head title="Orders" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold">Orders Board</h2>
        <Button @click="openCreateDialog">
          <PlusCircle class="h-4 w-4 mr-2" />
          New Order
        </Button>
      </div>

      <!-- Kanban Board -->
      <div class="grid grid-cols-4 gap-4 flex-1">
        <div
            v-for="column in columns"
            :key="column.key"
            class="flex flex-col min-h-0"
        >
          <!-- Column Header -->
          <div
              class="rounded-t-lg border-t-4 px-3 py-2 flex-shrink-0 transition-colors duration-200"
              :class="[column.color, column.bg]"
          >
            <div class="flex items-center justify-between">
              <h3 class="font-semibold text-sm uppercase tracking-wide">
                {{ column.label }}
              </h3>
              <span class="rounded-full bg-white dark:bg-gray-800 px-2 py-0.5 text-xs font-medium">
                {{ getColumnCount(column.key) }}
              </span>
            </div>
          </div>

          <!-- Column Body -->
          <div
              class="flex-1 rounded-b-lg border border-t-0 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 overflow-y-auto transition-all duration-200"
              :class="{
              'ring-2 ring-primary/50 ring-inset': isDragging,
              [column.hoverBg]: isDragging,
            }"
          >
            <draggable
                v-model="kanbanData[column.key]"
                group="orders"
                item-key="id"
                class="p-2 space-y-2 min-h-full"
                ghost-class="kanban-ghost"
                drag-class="kanban-drag"
                chosen-class="kanban-chosen"
                animation="200"
                @start="handleDragStart"
                @end="handleDragEnd"
                @change="(evt: any) => handleDragChange(column.key, evt)"
            >
              <template #item="{ element: order }">
                <div class="kanban-card bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-200 cursor-grab active:cursor-grabbing overflow-hidden">

                  <!-- Product Image Header -->
                  <div class="relative h-32 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <img
                        v-if="order.items?.[0]?.product?.images?.[0]?.path"
                        :src="`/storage/${order.items[0].product.images[0].path}`"
                        :alt="order.items[0].product?.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                      <Package class="h-12 w-12 text-gray-400" />
                    </div>
                    <!-- Rest of the overlay code remains the same -->
                    <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70"></div>

                    <!-- ... rest of the card ... -->
                  </div>

                  <!-- Card Content -->
                  <div class="p-3">
                    <!-- Customer Info -->
                    <div class="space-y-1 mb-3">
                      <div class="flex items-center gap-2 text-sm">
                        <User class="h-3.5 w-3.5 text-gray-400" />
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

                    <!-- Card Footer -->
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                      <span class="text-xs text-gray-400">{{ formatDate(order.created_at) }}</span>
                      <div class="flex items-center gap-1">
                        <button
                            @click.stop="openViewDialog(order)"
                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                            title="View"
                        >
                          <Eye class="h-4 w-4 text-gray-500" />
                        </button>
                        <button
                            @click.stop="openEditDialog(order)"
                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                            title="Edit"
                        >
                          <Edit class="h-4 w-4 text-blue-500" />
                        </button>
                        <button
                            @click.stop="openDeleteDialog(order)"
                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                            title="Delete"
                        >
                          <Trash2 class="h-4 w-4 text-red-500" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </draggable>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:open="isCreateDialogOpen">
      <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto" @interact-outside="(e: Event) => e.preventDefault()">
        <DialogHeader>
          <DialogTitle>Create New Order</DialogTitle>
        </DialogHeader>
        <form @submit.prevent="handleCreateSubmit">
          <div class="grid gap-4 py-4">
            <!-- Customer Details -->
            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label>Customer Name</Label>
                <Input v-model="createForm.customer_name" placeholder="John Doe" />
              </div>
              <div class="grid gap-2">
                <Label>M-Pesa Number *</Label>
                <Input v-model="createForm.mpesa_number" placeholder="254712345678" required />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label>Town *</Label>
                <Input v-model="createForm.town" placeholder="Nairobi" required />
              </div>
              <div class="grid gap-2">
                <Label>M-Pesa Code</Label>
                <Input v-model="createForm.mpesa_code" placeholder="SH12X3Y4Z5" />
              </div>
            </div>

            <div class="grid gap-2">
              <Label>Description *</Label>
              <Input v-model="createForm.description" placeholder="Order details" required />
            </div>

            <!-- Items Section -->
            <div class="border-t pt-4">
              <Label class="text-base font-semibold mb-3 block">Order Items</Label>

              <!-- Add Item Form -->
              <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg space-y-3 mb-3">
                <div class="grid grid-cols-2 gap-3">
                  <div class="grid gap-2">
                    <Label>Product *</Label>
                    <Select :model-value="currentItem.product_id?.toString()" @update:model-value="onProductSelect">
                      <SelectTrigger><SelectValue placeholder="Select product" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="product in products" :key="product.id" :value="product.id.toString()">
                          {{ product.name }} - KES {{ product.price }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="grid gap-2">
                    <Label>Quantity *</Label>
                    <Input v-model.number="currentItem.quantity" type="number" min="1" />
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                  <div class="grid gap-2">
                    <Label>Price *</Label>
                    <Input v-model.number="currentItem.price" type="number" step="0.01" min="0" />
                  </div>
                  <div class="grid gap-2" v-if="availableSizes.length > 0">
                    <Label>Size</Label>
                    <Select v-model="currentItem.size">
                      <SelectTrigger><SelectValue placeholder="Select size" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="size in availableSizes" :key="size" :value="size">
                          {{ size }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="grid gap-2" v-if="availableColors.length > 0">
                    <Label>Color</Label>
                    <Select v-model="currentItem.color">
                      <SelectTrigger><SelectValue placeholder="Select color" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="color in availableColors" :key="color" :value="color">
                          {{ color }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <Button type="button" @click="addItem" variant="outline" class="w-full">
                  Add Item
                </Button>
              </div>

              <!-- Items List -->
              <div v-if="createForm.items.length > 0" class="space-y-2">
                <div v-for="(item, index) in createForm.items" :key="index" class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded border">
                  <div class="flex-1">
                    <p class="font-medium text-sm">{{ getProductName(item.product_id) }}</p>
                    <p class="text-xs text-gray-500">
                      Qty: {{ item.quantity }} × KES {{ item.price }}
                      <span v-if="item.size"> • Size: {{ item.size }}</span>
                      <span v-if="item.color"> • Color: {{ item.color }}</span>
                    </p>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="font-semibold text-sm">KES {{ (item.quantity * item.price).toLocaleString() }}</span>
                    <button type="button" @click="removeItem(index)" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                      <X class="h-4 w-4 text-red-500" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Order Summary -->
            <div class="border-t pt-4">
              <div class="flex justify-between items-center mb-4">
                <span class="font-semibold">Total Amount:</span>
                <span class="text-xl font-bold">KES {{ createForm.amount.toLocaleString() }}</span>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                  <Label>Payment Status</Label>
                  <Select v-model="createForm.payment_status">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="pending">Pending</SelectItem>
                      <SelectItem value="paid">Paid</SelectItem>
                      <SelectItem value="failed">Failed</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="grid gap-2">
                  <Label>Order Status</Label>
                  <Select v-model="createForm.status">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="pending">Pending</SelectItem>
                      <SelectItem value="processing">Processing</SelectItem>
                      <SelectItem value="completed">Completed</SelectItem>
                      <SelectItem value="cancelled">Cancelled</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div class="grid gap-2 mt-4">
                <Label>Tracking Number</Label>
                <Input v-model="createForm.tracking_number" placeholder="Optional" />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" @click="closeCreateDialog">Cancel</Button>
            <Button type="submit" :disabled="createForm.processing">Create Order</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- View Dialog -->
    <Dialog v-model:open="isViewDialogOpen">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Order Details</DialogTitle>
          <DialogDescription v-if="viewingOrder">
            Order #{{ viewingOrder.uuid.slice(0, 8) }}
          </DialogDescription>
        </DialogHeader>

        <div v-if="viewingOrder" class="grid gap-4 py-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-muted-foreground">Customer</Label>
              <p class="font-medium">{{ viewingOrder.customer_name || 'N/A' }}</p>
            </div>
            <div>
              <Label class="text-muted-foreground">M-Pesa Number</Label>
              <p class="font-medium">{{ viewingOrder.mpesa_number }}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-muted-foreground">Amount</Label>
              <p class="font-medium">KES {{ Number(viewingOrder.amount).toLocaleString() }}</p>
            </div>
            <div>
              <Label class="text-muted-foreground">M-Pesa Code</Label>
              <p class="font-medium font-mono">{{ viewingOrder.mpesa_code || 'N/A' }}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-muted-foreground">Town</Label>
              <p class="font-medium">{{ viewingOrder.town }}</p>
            </div>
            <div>
              <Label class="text-muted-foreground">Tracking Number</Label>
              <p class="font-medium font-mono">{{ viewingOrder.tracking_number || 'N/A' }}</p>
            </div>
          </div>
          <div>
            <Label class="text-muted-foreground">Description</Label>
            <p class="text-sm mt-1">{{ viewingOrder.description }}</p>
          </div>
          <div v-if="viewingOrder.items && viewingOrder.items.length > 0">
            <Label class="text-muted-foreground mb-2 block">Items ({{ viewingOrder.items.length }})</Label>
            <div class="rounded-lg border overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Product</TableHead>
                    <TableHead>Size</TableHead>
                    <TableHead>Color</TableHead>
                    <TableHead>Qty</TableHead>
                    <TableHead>Price</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="item in viewingOrder.items" :key="item.id">
                    <TableCell>{{ item.product?.name || 'N/A' }}</TableCell>
                    <TableCell>{{ item.size || '-' }}</TableCell>
                    <TableCell>{{ item.color || '-' }}</TableCell>
                    <TableCell>{{ item.quantity }}</TableCell>
                    <TableCell>KES {{ Number(item.price).toLocaleString() }}</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="closeViewDialog">Close</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit Dialog -->
    <Dialog v-model:open="isEditDialogOpen">
      <DialogContent class="sm:max-w-[450px]" @interact-outside="(e: Event) => e.preventDefault()">
        <DialogHeader>
          <DialogTitle>Update Order</DialogTitle>
          <DialogDescription v-if="editingOrder">
            Order #{{ editingOrder.uuid.slice(0, 8) }}
          </DialogDescription>
        </DialogHeader>
        <form @submit.prevent="handleEditSubmit">
          <div class="grid gap-4 py-4">
            <div class="grid gap-2">
              <Label>Order Status</Label>
              <Select v-model="editForm.status" required>
                <SelectTrigger><SelectValue placeholder="Select status" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="processing">Processing</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-2">
              <Label>Payment Status</Label>
              <Select v-model="editForm.payment_status" required>
                <SelectTrigger><SelectValue placeholder="Select payment status" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="paid">Paid</SelectItem>
                  <SelectItem value="failed">Failed</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-2">
              <Label>Tracking Number</Label>
              <Input v-model="editForm.tracking_number" placeholder="Enter tracking number" />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" @click="closeEditDialog">Cancel</Button>
            <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Delete Dialog -->
    <AlertDialog v-model:open="isDeleteDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
          <AlertDialogDescription>
            This will permanently delete order
            <span v-if="deletingOrder" class="font-semibold font-mono">#{{ deletingOrder.uuid.slice(0, 8) }}</span>
            and all its items.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="closeDeleteDialog">Cancel</AlertDialogCancel>
          <AlertDialogAction @click="handleDeleteConfirm" class="bg-red-600 hover:bg-red-700">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>

<style>
/* Ghost element - placeholder where item will be dropped */
.kanban-ghost {
  opacity: 0.4;
  background: hsl(var(--primary) / 0.1) !important;
  border: 2px dashed hsl(var(--primary)) !important;
  border-radius: 0.5rem;
}

/* The element being dragged */
.kanban-drag {
  opacity: 1 !important;
  transform: rotate(3deg) scale(1.02);
  box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.15) !important;
  z-index: 9999;
}

/* The chosen element before dragging starts */
.kanban-chosen {
  box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1) !important;
}

/* Smooth transitions for cards moving out of the way */
.kanban-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

/* Sortable animation for items moving */
.sortable-ghost {
  opacity: 0;
}
</style>