package com.barbershop.pos.presentation.transaction

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.barbershop.pos.databinding.FragmentTransactionBinding
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch

@AndroidEntryPoint
class TransactionFragment : Fragment() {

    private var _binding: FragmentTransactionBinding? = null
    private val binding get() = _binding!!
    private val viewModel: TransactionViewModel by viewModels()
    private lateinit var adapter: TransactionListAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentTransactionBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = TransactionListAdapter(
            onDelete = { localId -> viewModel.deleteTransaction(localId) }
        )
        binding.transactionList.apply {
            layoutManager = LinearLayoutManager(requireContext())
            adapter = this@TransactionFragment.adapter
        }

        binding.addButton.setOnClickListener {
            AddTransactionBottomSheet {
                viewModel.addTransaction(it.first, it.second)
            }.show(childFragmentManager, "add_transaction")
        }

        lifecycleScope.launch {
            viewModel.transactions.collect { txs ->
                adapter.submitList(txs)
            }
        }

        lifecycleScope.launch {
            viewModel.addState.collect { state ->
                when (state) {
                    is AddTransactionState.Success -> {
                        Toast.makeText(requireContext(), "Transaksi ditambahkan", Toast.LENGTH_SHORT).show()
                        viewModel.resetAddState()
                    }
                    is AddTransactionState.Error -> {
                        Toast.makeText(requireContext(), state.message, Toast.LENGTH_SHORT).show()
                        viewModel.resetAddState()
                    }
                    else -> {}
                }
            }
        }

        lifecycleScope.launch {
            viewModel.pendingSyncCount.collect { count ->
                binding.pendingCount.text = "$count data belum sinkron"
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
