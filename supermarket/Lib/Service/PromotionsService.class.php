<?php

/**
 * Class PromotionsService
 * 商品促销相关业务逻辑
 * 促销功能的操作者：1：超级管理员，2：分店负责人，（其他人员不能执行该功能）
 */
class PromotionsService{
    /**
     * 获取促销列表
     * @param $map
     * @return array
     */
    public function getList($map){
        /*
         * 1：超级管理员：可以查看所有分店的促销信息
         * 2：分店负责人：只可以查看本分店的促销信息，即在查询时带入分店id进行查询
         */
        //取出员工所属的分店信息
        $branchInfo = session("branch_info");
        //是分店负责人，带上分店ID查询
        $map["branch.id"] = $branchInfo["id"];
        //如果是管理员，可惜查询所有促销信息
        if( session( C("ADMIN_AUTH_KEY") ) == true ){
            //如果是管理员
            unset($map["branch.id"]);
        }
        $model = D("Promotions");
        $count = $model->where($map)->count("id");
        $table = "promotions as p,goods as g,branch as b";
        $result = array();
        $fields = "p.*,g.name as goods_name,g.sales_price,b.name as branch_name";
        if($count > 0 ){
            import("@.ORG.Util.Page");
            $page = new Page($count,5);
            $result["list"] = $model->table($table)->field($fields)->where("p.goods_id=g.id and p.branch_id=b.id")->order("p.time_end desc")->limit($page->firstRow.','.$page->listRows)->select();
            $result["page"] = $page->show();
        }
        return $result;
    }
    /**
     * 商品列表下发布折扣
     * @return array
     * @throws ThinkException
     */
    public function release(){
        $goods_id = $_GET["goods_id"];
        $branch_id = $_SESSION["staff_info"]["branch_id"];
        if(!isset($goods_id) && !isset($branch_id)){
            throw new ThinkException("分店ID出错或商品ID出错!");
        }
        //查询该商品在此期间是否存在折扣，存在则不允许在添加折扣，否则添加
        $goods = D("Promotions")->where("goods_id=".$goods_id)->find();
        $result = array();
        if(true == $goods){
            $now = time();
            /*
             * 判断当前时间是否在商品促销期间
             * 在商品促销时间：提示不能在添加促销信息，
             * 该商品促销时间已过：可以添加商品促销
             */
            //在促销期间
//            trace($goods["time_start"] );
//            trace($now);
//            trace($goods["time_end"]);
            if(($goods["time_start"] < $now) && ($now < $goods["time_end"])){
                throw new ThinkException("该商品在此期间存在促销！");
            }
        }
        $result["branch"] = M("Branch")->getById($branch_id);
        $result["goods"] = M("Goods")->getById($goods_id);
        if(false == $result["branch"] && false == $result["goods"]){
            throw new ThinkException("分店不存在或没有打折的商品!");
        }
        return $result;
    }

    /**
     * 插入促销信息
     * @throws ThinkException
     */
    public function insert(){
        $model = D("Promotions");
        $vo = $model->create();
        if(false === $vo){
            throw new ThinkException($model->getError());
        }
        //开启事务
        $model->startTrans();
        $id = $model->add();
        if( false === $id ){
            //事务回滚
            $model->rollback();
            throw new ThinkException("添加商品信息出错！".$model->getError());
        }
        //提交事务
        $model->commit();
    }

    /**
     * 编辑
     *
     * @return array
     * @throws ThinkException
     */
    public function edit($branchId,$promotionId){
        $model = M("Promotions");
        $result = array();
        $result["promotions"] = $model->getById($promotionId);
        //无权限删除其他分公司折扣信息
        if( $result["promotions"]["branch_id"] == $branchId){
            $result["goods"] = D("Goods")->getById($result["promotions"]["goods_id"]);
            if(false == $result){
                throw new ThinkException("修改商品折扣出错！".$model->getError());
            }
        }else{
            $result["goods"]=false;
        }
        return $result;
    }

    /**
     * 更新促销信息
     * @throws ThinkException
     */
    public function update(){
        $model = D("Promotions");
        $vo = $model->create();
        if(false === $vo){
            throw new ThinkException($model->getError());
        }
        //开启事务
        $model->startTrans();
        $id = $model->save();
        if( false === $id ){
            //事务回滚
            $model->rollback();
            throw new ThinkException("修改商品信息出错！".$model->getError());
        }
        //提交事务
        $model->commit();
    }

    /**
     * 根据分店ID和促销ID删除促销信息
     * @param $branchId     分店ID
     * @param $promotionId      促销ID
     * @throws ThinkException
     */
    public function del($branchId,$promotionId){
        $model = D("Promotions");
        $result  = $model->getById($promotionId);
        //开启事务
        $model->startTrans();
        //登录员工所属分店是否与删除折扣信息的分店标识一致，才允许删除
        if($branchId == $result["branch_id"]){
            $vo = $model->delete($promotionId);
            if( false === $vo ){
                //事务回滚
                $model->rollback();
                throw new ThinkException("删除折扣信息出错！".$model->getError());
            }
        }
        //提交事务
        $model->commit();
    }



}