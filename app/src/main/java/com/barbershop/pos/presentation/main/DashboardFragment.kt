package com.barbershop.pos.presentation.main

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import com.barbershop.pos.databinding.FragmentDashboardBinding
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch

@AndroidEntryPoint
class DashboardFragment : Fragment() {

    private var _binding: FragmentDashboardBinding? = null
    private val binding get() = _binding!!
    private val viewModel: DashboardViewModel by viewModels()

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentDashboardBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        binding.syncButton.setOnClickListener {
            viewModel.syncNow()
        }

        lifecycleScope.launch {
            viewModel.dashboardState.collect { state ->
                when (state) {
                    is DashboardState.Loading -> {
                        binding.contentGroup.visibility = View.GONE
                        binding.loadingProgress.visibility = View.VISIBLE
                    }
                    is DashboardState.Success -> {
                        binding.loadingProgress.visibility = View.GONE
                        binding.contentGroup.visibility = View.VISIBLE
                        binding.revenueCard.text = "Rp ${state.totalRevenue:,}"
                        binding.customerCard.text = "${state.customerCount} Pelanggan"
                        binding.commissionCard.text = "Rp ${state.barberCommission:,}"
                        binding.pendingSyncCount.text = "${state.pendingSyncCount} data"
                    }
                    is DashboardState.Error -> {
                        binding.contentGroup.visibility = View.VISIBLE
                        binding.loadingProgress.visibility = View.GONE
                        Toast.makeText(requireContext(), state.message, Toast.LENGTH_SHORT).show()
                    }
                }
            }
        }

        lifecycleScope.launch {
            viewModel.syncStatus.collect { status ->
                when (status) {
                    is SyncStatus.Syncing -> binding.syncButton.isEnabled = false
                    is SyncStatus.Success -> {
                        binding.syncButton.isEnabled = true
                        Toast.makeText(requireContext(), status.message, Toast.LENGTH_SHORT).show()
                    }
                    is SyncStatus.Failed -> {
                        binding.syncButton.isEnabled = true
                        Toast.makeText(requireContext(), status.message, Toast.LENGTH_SHORT).show()
                    }
                    else -> binding.syncButton.isEnabled = true
                }
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
