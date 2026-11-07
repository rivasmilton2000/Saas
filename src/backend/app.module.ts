import { Controller, Get, Inject, Module } from '@nestjs/common';
import { ConexionModule, MysqlService } from '../../conexion/conexiones';
import { AuthModule } from './auth';
import { BillingModule } from './billing';
import { PublicModule } from './public';
import { ok } from './common';
import { BusinessModule } from './business';
import { AdminModule } from './admin';
import { AccountModule } from './account';
import { AccountingModule } from './accounting';
import { DashboardModule } from './dashboard-api';

@Controller('api')
class HealthController {
  constructor(@Inject(MysqlService) private readonly db: MysqlService) {}
  @Get('health') async health() {
    await this.db.rows('SELECT 1 AS ok');
    return ok({status: 'ok',database: 'mysql'});
  }
}
@Module({imports: [ConexionModule, AuthModule, PublicModule, BillingModule, BusinessModule, AdminModule, AccountModule, AccountingModule, DashboardModule], controllers: [HealthController]})
export class AppModule {}
