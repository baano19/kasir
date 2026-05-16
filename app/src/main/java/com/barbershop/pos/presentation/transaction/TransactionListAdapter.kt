package com.barbershop.pos.presentation.transaction

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.barbershop.pos.data.local.entity.TransactionEntity
import com.barbershop.pos.data.local.entity.SyncStatus
import com.barbershop.pos.databinding.ItemTransactionBinding

class TransactionListAdapter(
    private val onDelete: (String) -> Unit
) : ListAdapter<TransactionEntity, TransactionListAdapter.ViewHolder>(TransactionDiffUtil()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ItemTransactionBinding.inflate(
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
        private val binding: ItemTransactionBinding,
        private val onDelete: (String) -> Unit
    ) : RecyclerView.ViewHolder(binding.root) {
        fun bind(transaction: TransactionEntity) {
            binding.apply {
                serviceName.text = transaction.serviceName
                amount.text = "Rp ${transaction.amount:,}"
                syncStatusBadge.text = when (transaction.syncStatus) {
                    SyncStatus.PENDING -> "Pending"
                    SyncStatus.SYNCED -> "Sinkron"
                    SyncStatus.FAILED -> "Gagal"
                    SyncStatus.CONFLICT -> "Konflik"
                }
                deleteButton.setOnClickListener {
                    onDelete(transaction.localId)
                }
            }
        }
    }
}

class TransactionDiffUtil : DiffUtil.ItemCallback<TransactionEntity>() {
    override fun areItemsTheSame(
        oldItem: TransactionEntity,
        newItem: TransactionEntity
    ): Boolean = oldItem.localId == newItem.localId

    override fun areContentsTheSame(
        oldItem: TransactionEntity,
        newItem: TransactionEntity
    ): Boolean = oldItem == newItem
}
