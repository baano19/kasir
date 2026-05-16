package com.barbershop.pos.presentation.expense

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.barbershop.pos.databinding.FragmentExpenseBinding
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch

@AndroidEntryPoint
class ExpenseFragment : Fragment() {

    private var _binding: FragmentExpenseBinding? = null
    private val binding get() = _binding!!
    private val viewModel: ExpenseViewModel by viewModels()
    private lateinit var adapter: ExpenseListAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentExpenseBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = ExpenseListAdapter(
            onDelete = { localId -> viewModel.deleteExpense(localId) }
        )
        binding.expenseList.apply {
            layoutManager = LinearLayoutManager(requireContext())
            adapter = this@ExpenseFragment.adapter
        }

        binding.operasionalTab.setOnClickListener {
            viewModel.setCategory("operasional")
        }
        binding.makanTab.setOnClickListener {
            viewModel.setCategory("makan")
        }
        binding.semuaTab.setOnClickListener {
            viewModel.setCategory("semua")
        }

        binding.addButton.setOnClickListener {
            AddExpenseDialog {
                viewModel.addExpense(it.first, it.second, it.third)
            }.show(childFragmentManager, "add_expense")
        }

        lifecycleScope.launch {
            viewModel.allExpenses.collect { expenses ->
                adapter.submitList(expenses)
            }
        }

        lifecycleScope.launch {
            viewModel.addState.collect { state ->
                when (state) {
                    is AddExpenseState.Success -> {
                        Toast.makeText(requireContext(), "Pengeluaran ditambahkan", Toast.LENGTH_SHORT).show()
                        viewModel.resetAddState()
                    }
                    is AddExpenseState.Error -> {
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
