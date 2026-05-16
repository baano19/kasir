package com.barbershop.pos.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import com.barbershop.pos.data.local.dao.*
import com.barbershop.pos.data.local.entity.*

@Database(
    entities = [
        TransactionEntity::class,
        ExpenseEntity::class,
        UserEntity::class,
        BranchEntity::class,
        ServiceEntity::class
    ],
    version = 1,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun transactionDao(): TransactionDao
    abstract fun expenseDao(): ExpenseDao
    abstract fun userDao(): UserDao
    abstract fun branchDao(): BranchDao
    abstract fun serviceDao(): ServiceDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "barbershop_pos.db"
                ).build()
                INSTANCE = instance
                instance
            }
        }
    }
}
