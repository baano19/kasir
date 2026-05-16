package com.barbershop.pos.di

import android.content.Context
import com.barbershop.pos.data.local.dao.BranchDao
import com.barbershop.pos.data.local.dao.ExpenseDao
import com.barbershop.pos.data.local.dao.ServiceDao
import com.barbershop.pos.data.local.dao.TransactionDao
import com.barbershop.pos.data.local.dao.UserDao
import com.barbershop.pos.data.remote.ApiService
import com.barbershop.pos.domain.repository.AuthRepository
import com.barbershop.pos.domain.repository.ExpenseRepository
import com.barbershop.pos.domain.repository.MasterDataRepository
import com.barbershop.pos.domain.repository.TransactionRepository
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import java.util.*
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object RepositoryModule {

    @Singleton
    @Provides
    fun provideDeviceId(): String {
        return UUID.randomUUID().toString()
    }

    @Singleton
    @Provides
    fun provideAuthRepository(
        @ApplicationContext context: Context,
        apiService: ApiService
    ): AuthRepository {
        return AuthRepository(context, apiService)
    }

    @Singleton
    @Provides
    fun provideTransactionRepository(
        transactionDao: TransactionDao,
        apiService: ApiService,
        deviceId: String
    ): TransactionRepository {
        return TransactionRepository(transactionDao, apiService, deviceId)
    }

    @Singleton
    @Provides
    fun provideExpenseRepository(
        expenseDao: ExpenseDao,
        apiService: ApiService,
        deviceId: String
    ): ExpenseRepository {
        return ExpenseRepository(expenseDao, apiService, deviceId)
    }

    @Singleton
    @Provides
    fun provideMasterDataRepository(
        branchDao: BranchDao,
        serviceDao: ServiceDao,
        userDao: UserDao,
        apiService: ApiService
    ): MasterDataRepository {
        return MasterDataRepository(branchDao, serviceDao, userDao, apiService)
    }
}
