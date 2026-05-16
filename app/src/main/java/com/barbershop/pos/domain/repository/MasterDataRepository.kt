package com.barbershop.pos.domain.repository

import com.barbershop.pos.data.local.dao.BranchDao
import com.barbershop.pos.data.local.dao.ServiceDao
import com.barbershop.pos.data.local.dao.UserDao
import com.barbershop.pos.data.local.entity.BranchEntity
import com.barbershop.pos.data.local.entity.ServiceEntity
import com.barbershop.pos.data.local.entity.UserEntity
import com.barbershop.pos.data.remote.ApiService
import kotlinx.coroutines.flow.Flow

class MasterDataRepository(
    private val branchDao: BranchDao,
    private val serviceDao: ServiceDao,
    private val userDao: UserDao,
    private val apiService: ApiService
) {
    fun getAllBranches(): Flow<List<BranchEntity>> {
        return branchDao.getAllBranches()
    }

    fun getServicesByBranch(branchId: Int): Flow<List<ServiceEntity>> {
        return serviceDao.getServicesByBranch(branchId)
    }

    fun getAllUsers(): Flow<List<UserEntity>> {
        return userDao.getAllUsers()
    }

    fun getUsersByBranch(branchId: Int): Flow<List<UserEntity>> {
        return userDao.getUsersByBranch(branchId)
    }

    suspend fun syncMasterData(): Result<Unit> {
        return try {
            val response = apiService.pullSync()
            if (response.success && response.data != null) {
                val data = response.data

                branchDao.deleteAll()
                data.branches.forEach { branch ->
                    branchDao.insertBranch(
                        BranchEntity(
                            id = branch.id,
                            name = branch.name,
                            address = branch.address ?: "",
                            mealAllowance = branch.meal_allowance
                        )
                    )
                }

                serviceDao.deleteAll()
                data.services.forEach { service ->
                    serviceDao.insertService(
                        ServiceEntity(
                            id = service.id,
                            name = service.name,
                            price = service.price,
                            branchId = service.branch_id
                        )
                    )
                }

                userDao.deleteAll()
                data.users.forEach { user ->
                    userDao.insertUser(
                        UserEntity(
                            id = user.id,
                            username = user.username,
                            name = user.name,
                            role = user.role,
                            branchId = user.branch_id,
                            mealAllowance = user.meal_allowance
                        )
                    )
                }

                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Sync master data gagal"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
