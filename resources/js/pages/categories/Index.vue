<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit, Trash2, Plus } from 'lucide-vue-next';
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
import { ref, computed } from 'vue'
import Pagination from '@/components/Pagination.vue'

const props = defineProps<{
  categories?: {
    data: any[]
    links: any[]
  }
  allCategories?: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'Categories' },
]

const categoriesList = computed(() => props.categories?.data || [])
const paginationLinks = computed(() => props.categories?.links || [])

// Add Modal
const isAddDialogOpen = ref(false)
const addForm = useForm({
  name: '',
  parent_id: '',
})

const openAddDialog = () => {
  addForm.reset()
  addForm.clearErrors()
  isAddDialogOpen.value = true
}

const handleAddSubmit = () => {
  addForm.post('/categories', {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Category Added', {
        description: `${addForm.name} has been added successfully.`,
      })
      isAddDialogOpen.value = false
      addForm.reset()
    },
    onError: () => {
      toast.error('Validation Error', {
        description: 'Please check the form for errors.',
      })
    },
  })
}

const closeAddDialog = () => {
  isAddDialogOpen.value = false
  addForm.reset()
  addForm.clearErrors()
}

// Edit Modal
const isEditDialogOpen = ref(false)
const editingCategory = ref<any>(null)
const editForm = useForm({
  id: '',
  name: '',
  parent_id: '',
})

const openEditDialog = (category: any) => {
  editingCategory.value = category
  editForm.id = category.id
  editForm.name = category.name
  editForm.parent_id = category.parent_id?.toString() || ''
  editForm.clearErrors()
  isEditDialogOpen.value = true
}

const handleEditSubmit = () => {
  editForm.put(`/categories/${editForm.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Category Updated', {
        description: `${editForm.name} has been updated successfully.`,
      })
      isEditDialogOpen.value = false
      editingCategory.value = null
    },
    onError: () => {
      toast.error('Validation Error', {
        description: 'Please check the form for errors.',
      })
    },
  })
}

const closeEditDialog = () => {
  isEditDialogOpen.value = false
  editingCategory.value = null
  editForm.reset()
  editForm.clearErrors()
}

// Delete Modal
const isDeleteDialogOpen = ref(false)
const deletingCategory = ref<any>(null)

const openDeleteDialog = (category: any) => {
  deletingCategory.value = category
  isDeleteDialogOpen.value = true
}

const handleDeleteConfirm = () => {
  if (!deletingCategory.value) return

  router.delete(`/categories/${deletingCategory.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Category Deleted', {
        description: `${deletingCategory.value.name} has been deleted successfully.`,
      })
      isDeleteDialogOpen.value = false
      deletingCategory.value = null
    },
    onError: () => {
      toast.error('Delete Failed', {
        description: 'Unable to delete category. It may have subcategories.',
      })
    },
  })
}

const closeDeleteDialog = () => {
  isDeleteDialogOpen.value = false
  deletingCategory.value = null
}
</script>

<template>
  <Head title="Categories" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
      <div class="relative min-h-[100vh] flex-1 rounded-xl border border-gray-200 bg-white md:min-h-min dark:border-gray-700 dark:bg-gray-800">
        <div class="p-6">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold">Categories</h2>
            <Button @click="openAddDialog">
              <Plus class="mr-2 h-4 w-4" />
              Add Category
            </Button>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>Parent</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="categoriesList.length === 0">
                  <TableCell colspan="4" class="text-center text-gray-500 dark:text-gray-400 h-24">
                    No categories found.
                  </TableCell>
                </TableRow>
                <TableRow v-for="(category, index) in categoriesList" :key="category.id">
                  <TableCell class="font-medium">{{ index + 1 }}</TableCell>
                  <TableCell>{{ category.name }}</TableCell>
                  <TableCell class="text-gray-500 dark:text-gray-400">
                    {{ category.parent?.name ?? '—' }}
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button
                          @click="openEditDialog(category)"
                          class="inline-flex items-center gap-1 rounded-md bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800"
                      >
                        <Edit class="h-4 w-4" />
                        Edit
                      </button>
                      <button
                          @click="openDeleteDialog(category)"
                          class="inline-flex items-center gap-1 rounded-md bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800"
                      >
                        <Trash2 class="h-4 w-4" />
                        Delete
                      </button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
              Showing {{ categoriesList.length }} categories on this page
            </div>
            <Pagination :links="paginationLinks" />
          </div>
        </div>
      </div>
    </div>

    <!-- Add Category Dialog -->
    <Dialog v-model:open="isAddDialogOpen">
      <DialogContent class="sm:max-w-[500px]" @interact-outside="(e) => e.preventDefault()">
        <DialogHeader>
          <DialogTitle>Add New Category</DialogTitle>
          <DialogDescription>Enter the category details below.</DialogDescription>
        </DialogHeader>

        <form @submit.prevent="handleAddSubmit">
          <div class="grid gap-4 py-4">
            <div class="grid gap-2">
              <Label for="add-name">Name</Label>
              <Input id="add-name" v-model="addForm.name" placeholder="Category name" required />
            </div>
            <div class="grid gap-2">
              <Label for="add-parent">Parent Category</Label>
              <Select v-model="addForm.parent_id">
                <SelectTrigger id="add-parent">
                  <SelectValue placeholder="None (top-level)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="cat in allCategories" :key="cat.id" :value="cat.id.toString()">
                    {{ cat.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeAddDialog">Cancel</Button>
            <Button type="submit">Save</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Edit Category Dialog -->
    <Dialog v-model:open="isEditDialogOpen">
      <DialogContent class="sm:max-w-[500px]" @interact-outside="(e) => e.preventDefault()">
        <DialogHeader>
          <DialogTitle>Edit Category</DialogTitle>
          <DialogDescription>Update the category details below.</DialogDescription>
        </DialogHeader>

        <form @submit.prevent="handleEditSubmit">
          <div class="grid gap-4 py-4">
            <div class="grid gap-2">
              <Label for="edit-name">Name</Label>
              <Input id="edit-name" v-model="editForm.name" placeholder="Category name" required />
            </div>
            <div class="grid gap-2">
              <Label for="edit-parent">Parent Category</Label>
              <Select v-model="editForm.parent_id">
                <SelectTrigger id="edit-parent">
                  <SelectValue placeholder="None (top-level)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                      v-for="cat in allCategories?.filter(c => c.id !== editForm.id)"
                      :key="cat.id"
                      :value="cat.id.toString()"
                  >
                    {{ cat.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeEditDialog">Cancel</Button>
            <Button type="submit">Save Changes</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Delete Category Alert Dialog -->
    <AlertDialog v-model:open="isDeleteDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
          <AlertDialogDescription>
            This will permanently delete
            <span v-if="deletingCategory" class="font-semibold">{{ deletingCategory.name }}</span>.
            Categories with subcategories cannot be deleted.
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