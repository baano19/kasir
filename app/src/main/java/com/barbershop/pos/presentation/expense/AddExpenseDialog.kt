package com.barbershop.pos.presentation.expense

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import com.google.android.material.bottomsheet.BottomSheetDialogFragment
import com.barbershop.pos.databinding.BottomSheetAddExpenseBinding

class AddExpenseDialog(
    private val onAddExpense: (Triple<String, String, String>) -> Unit
) : BottomSheetDialogFragment() {

    private var _binding: BottomSheetAddExpenseBinding? = null
    private val binding get() = _binding!!

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = BottomSheetAddExpenseBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        binding.addButton.setOnClickListener {
            val category = binding.categorySpinner.selectedItem.toString()
            val amount = binding.amountInput.text.toString()
            val notes = binding.notesInput.text.toString()
            if (category.isNotBlank() && amount.isNotBlank()) {
                onAddExpense(Triple(category, amount, notes))
                dismiss()
            }
        }

        binding.cancelButton.setOnClickListener {
            dismiss()
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
