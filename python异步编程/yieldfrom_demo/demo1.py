final_ret={}

def sales_sum(pro_name):
    total=0
    nums=[]
    while True:
        x=yield
        print(pro_name+"sale:",x)
        if not x:
            break
        total +=x
        nums.append(x)
    return total,nums
def middle(key):
    while True:
        final_ret[key]=yield from sales_sum(key)
        print(key+"complete")
def main():
    data_sets={
        "a":[100,200,300],
        "b":[400,500,600],
        "c":[700,800,900]
    }
    for key,data_sets in data_sets.items():
        print("start key:",key)
        m = middle(key)
        m.send(None)
        for value in data_sets:
            m.send(value)
        m.send(None)
    print("final_ret",final_ret)
if __name__ == '__main__':
    main()