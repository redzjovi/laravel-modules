<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\UserBalanceHistories\Models\UserBalanceHistories;

class CreateUserBalanceHistoriesTable extends Migration
{
    protected $model;

    public function __construct()
    {
        $this->model = new UserBalanceHistories;
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \Schema::create($this->model->getTable(), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->comment('users.id');
            $table->string('type')->comment('{ transaction }');
            $table->bigInteger('reference_id');
            $table->bigInteger('balance_start')->default(0)->nullable();

            $table->bigInteger('balance')->default(0)->nullable();
            $table->bigInteger('balance_end')->default(0)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \Schema::dropIfExists($this->model->getTable());
    }
}
