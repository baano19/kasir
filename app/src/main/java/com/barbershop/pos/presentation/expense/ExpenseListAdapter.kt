package com.barbershop.pos.presentation.expense

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.barbershop.pos.data.local.entity.ExpenseEntity
import com.barbershop.pos.data.local.entity.SyncStatus
import com.barbershop.pos.databinding.ItemExpenseBinding

class ExpenseListAdapter(
    private val onDelete: (String) -> Unit
) : ListAdapter<ExpenseEntity, ExpenseListAdapter.ViewHolder>(ExpenseDiffUtil()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ItemExpenseBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return ViewHolder(binding, onDelete)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    class ViewHolder(
        private val binding: ItemExpenseBinding,
        private val onDelete: (String) -> Unit
    ) : RecyclerView.ViewHolder(binding.root) {
        fun bind(expense: ExpenseEntity) {
            binding.apply {
                category.text = expense.category.uppercase()
                amount.text = "Rp ${expense.amount:,}"
                notes.text = expense.notes.ifEmpty { "-" }
                syncStatusBadge.text = when (expense.syncStatus) {
                    SyncStatus.PENDING -> "Pending"
                    SyncStatus.SYNCED -> "Sinkron"
                    SyncStatus.FAILED -> "Gagal"
                    SyncStatus.CONFLICT -> "Konflik"
                }
                deleteButton.setOnClickListener {
                    onDelete(expense.localId)
                }
            }
        }
    }
}

class ExpenseDiffUtil : DiffUtil.ItemCallback<ExpenseEntity>() {
    override fun areItemsTheSame(
        oldItem: ExpenseEntity,
        newItem: ExpenseEntity
    ): Boolean = oldItem.localId == newItem.localId

    override fun areContentsTheSame(
        oldItem: ExpenseEntity,
        newItem: ExpenseEntity
    ): Boolean = oldItem == newItem
}
